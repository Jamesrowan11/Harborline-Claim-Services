<?php

namespace App\Exports;

use App\Services\ReportRegistry;
use App\Services\Settings;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Formatted XLSX export used by every Print & Export Center report:
 * bold frozen header row, auto filters, currency/date formats, wrapped notes,
 * totals for currency columns, metadata header block (title, export time,
 * applied filters, prepared by), repeat header rows when printed, and a
 * confidentiality footer.
 */
class ReportExport implements FromArray, WithHeadings, WithStyles, WithColumnFormatting, WithEvents, WithTitle, WithProperties, ShouldAutoSize
{
    private const META_ROWS = 4; // title, generated, filters, blank

    private array $columns;
    private array $rows;

    public function __construct(
        private string $reportKey,
        private string $reportName,
        private array $filters,
        private string $preparedBy,
        ReportRegistry $registry,
    ) {
        $this->columns = $registry->columns($reportKey);
        $this->rows = $registry->rows($reportKey, $filters);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $appliedFilters = collect($this->filters)
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v, $k) => "$k: $v")
            ->implode(', ') ?: __('none');

        return [
            [$this->reportName.' — '.Settings::brand('name')],
            [__('Generated').': '.now()->format('Y-m-d H:i').'  •  '.__('Prepared by').': '.$this->preparedBy],
            [__('Applied filters').': '.$appliedFilters],
            array_map(fn ($c) => $c['label'], $this->columns),
        ];
    }

    public function title(): string
    {
        return mb_substr($this->reportName, 0, 31);
    }

    public function properties(): array
    {
        return [
            'title' => $this->reportName,
            'company' => (string) Settings::brand('legal_name'),
            'creator' => $this->preparedBy,
        ];
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach ($this->columns as $i => $column) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $formats[$letter] = match ($column['format'] ?? null) {
                ReportRegistry::CURRENCY => '"$"#,##0.00',
                ReportRegistry::DATE => NumberFormat::FORMAT_DATE_YYYYMMDD,
                default => NumberFormat::FORMAT_GENERAL,
            };
        }

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            self::META_ROWS => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $headerRow = self::META_ROWS;
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->columns));
                $lastRow = $headerRow + count($this->rows);

                // Frozen header + auto filter
                $sheet->freezePane('A'.($headerRow + 1));
                $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$headerRow}");

                // Wrap long text columns (notes, descriptions)
                $sheet->getStyle("A".($headerRow + 1).":{$lastColumn}{$lastRow}")
                    ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

                // Totals row for currency columns
                $currencyIndexes = collect($this->columns)->keys()
                    ->filter(fn ($i) => ($this->columns[$i]['format'] ?? null) === ReportRegistry::CURRENCY);
                if ($currencyIndexes->isNotEmpty() && count($this->rows) > 0) {
                    $totalsRow = $lastRow + 1;
                    $sheet->setCellValue('A'.$totalsRow, __('Totals'));
                    foreach ($currencyIndexes as $i) {
                        $letter = Coordinate::stringFromColumnIndex($i + 1);
                        $sheet->setCellValue($letter.$totalsRow, "=SUM({$letter}".($headerRow + 1).":{$letter}{$lastRow})");
                        $sheet->getStyle($letter.$totalsRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                    }
                    $sheet->getStyle("A{$totalsRow}:{$lastColumn}{$totalsRow}")->getFont()->setBold(true);
                }

                // Print setup: landscape, repeat header rows, confidentiality footer
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
                    ->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow)
                    ->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getHeaderFooter()->setOddFooter(
                    '&L'.str_replace('{name}', $this->preparedBy, (string) config('branding.confidentiality_footer'))
                    .'&R'.__('Page').' &P '.__('of').' &N'
                );
            },
        ];
    }
}
