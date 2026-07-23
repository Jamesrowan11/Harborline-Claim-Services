<?php

use App\Services\CaseNumberService;
use App\Services\Settings;

beforeEach(fn () => seedCore());

it('generates the documented case number format', function () {
    $service = app(CaseNumberService::class);

    expect($service->next('MD', 'AA', 2026))->toBe('HCS-2026-MD-AA-000001')
        ->and($service->next('MD', 'AA', 2026))->toBe('HCS-2026-MD-AA-000002')
        ->and($service->next('MD', 'HO', 2026))->toBe('HCS-2026-MD-HO-000001');
});

it('honors an administrator-modified format', function () {
    actingAsStaff();
    Settings::set('case_number_format', '{PREFIX}/{COUNTY}/{SEQ:4}');

    expect(app(CaseNumberService::class)->next('MD', 'AA', 2026))->toBe('HCS/AA/0001');
});
