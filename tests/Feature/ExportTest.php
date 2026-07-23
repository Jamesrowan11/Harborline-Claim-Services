<?php

use App\Models\AuditEvent;
use App\Models\ExportLog;
use App\Models\Payment;
use App\Services\ReportRegistry;

beforeEach(fn () => seedCore());

it('lists all reports in the Print & Export Center', function () {
    actingAsStaff();
    $this->get(route('portal.print.index'))
        ->assertOk()
        ->assertSee('Master Case Spreadsheet')
        ->assertSee('Revenue Spreadsheet')
        ->assertSee('Audit Report');
});

it('exports CSV with headers and rows and logs the export', function () {
    $user = actingAsStaff();
    createCase(['estimated_surplus' => 1234.56]);

    $response = $this->get(route('portal.print.export', ['report' => 'master_cases', 'format' => 'csv']));
    $response->assertOk();

    $csv = $response->streamedContent();
    expect($csv)->toContain('Case Number')->toContain('HCS-2026-MD-AA-000001');

    expect(ExportLog::query()->where('report_key', 'master_cases')->where('format', 'csv')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('event', 'export_created')->exists())->toBeTrue();
});

it('exports formatted XLSX with metadata and totals', function () {
    actingAsStaff();
    $case = createCase();
    Payment::query()->create([
        'case_id' => $case->id, 'payment_type' => 'funds_received',
        'amount' => 1000.50, 'occurred_on' => now()->toDateString(),
    ]);

    $response = $this->get(route('portal.print.export', ['report' => 'payments', 'format' => 'xlsx']));
    $response->assertOk();
    $response->assertDownload();
});

it('produces a print preview and a PDF', function () {
    actingAsStaff();
    createCase();

    $this->get(route('portal.print.show', ['report' => 'master_cases']))
        ->assertOk()->assertSee('Prepared by');

    $pdf = $this->get(route('portal.print.export', ['report' => 'master_cases', 'format' => 'pdf']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('pdf');
});

it('computes correct rows and currency columns in the registry', function () {
    $case = createCase();
    Payment::query()->create(['case_id' => $case->id, 'payment_type' => 'company_fee', 'amount' => 250.25, 'occurred_on' => now()->toDateString()]);
    Payment::query()->create(['case_id' => $case->id, 'payment_type' => 'funds_received', 'amount' => 1000.00, 'occurred_on' => now()->toDateString()]);

    $registry = app(ReportRegistry::class);
    $rows = $registry->rows('revenue');

    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0][2])->toBe(250.25);
});

it('blocks users without export permission', function () {
    actingAsStaff('Attorney'); // attorneys have no reports.export
    $this->get(route('portal.print.index'))->assertForbidden();
});
