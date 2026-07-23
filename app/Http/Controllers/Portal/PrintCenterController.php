<?php

namespace App\Http\Controllers\Portal;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\ExportLog;
use App\Services\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintCenterController extends Controller
{
    public function __construct(private ReportRegistry $registry)
    {
    }

    public function index()
    {
        return view('portal.print.index', ['reports' => $this->registry->all()]);
    }

    /** Print preview: HTML table in a print-friendly layout. */
    public function show(Request $request, string $report)
    {
        $definition = $this->registry->all()[$report] ?? abort(404);
        $filters = $request->only(['from', 'to', 'county']);

        return view('portal.print.show', [
            'reportKey' => $report,
            'reportName' => $definition['name'],
            'columns' => $this->registry->columns($report),
            'rows' => $this->registry->rows($report, $filters),
            'filters' => $filters,
            'orientation' => $request->query('orientation', 'landscape'),
            'paper' => $request->query('paper', 'letter'),
        ]);
    }

    public function export(Request $request, string $report)
    {
        $definition = $this->registry->all()[$report] ?? abort(404);
        $format = $request->query('format', 'xlsx');
        $filters = $request->only(['from', 'to', 'county']);
        $filename = $report.'-'.now()->format('Ymd-Hi');

        $rows = $this->registry->rows($report, $filters);

        ExportLog::query()->create([
            'user_id' => auth()->id(),
            'report_key' => $report,
            'format' => $format,
            'filters' => $filters,
            'row_count' => count($rows),
            'created_at' => now(),
        ]);
        AuditEvent::record('export_created', null, [], ['report' => $report, 'format' => $format, 'rows' => count($rows)]);

        return match ($format) {
            'csv' => $this->csv($report, $rows, $filename),
            'pdf' => $this->pdf($report, $definition['name'], $rows, $filters, $request->query('orientation', 'landscape'), $request->query('paper', 'letter'), $filename),
            default => Excel::download(
                new ReportExport($report, $definition['name'], $filters, auth()->user()->name, $this->registry),
                $filename.'.xlsx',
            ),
        };
    }

    private function csv(string $report, array $rows, string $filename): StreamedResponse
    {
        $columns = $this->registry->columns($report);

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map(fn ($c) => $c['label'], $columns));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function pdf(string $report, string $name, array $rows, array $filters, string $orientation, string $paper, string $filename)
    {
        $pdf = Pdf::loadView('pdf.report', [
            'reportName' => $name,
            'columns' => $this->registry->columns($report),
            'rows' => $rows,
            'filters' => $filters,
            'preparedBy' => auth()->user()->name,
        ])->setPaper(in_array($paper, ['letter', 'legal'], true) ? $paper : 'letter',
            $orientation === 'portrait' ? 'portrait' : 'landscape');

        return $pdf->download($filename.'.pdf');
    }
}
