<?php

beforeEach(fn () => seedCore());

it('lets administrators reach admin settings', function () {
    actingAsStaff('Company Administrator');
    $this->get(route('portal.admin.settings'))->assertOk();
});

it('blocks researchers from admin settings and payments', function () {
    actingAsStaff('Researcher');
    $this->get(route('portal.admin.settings'))->assertForbidden();
    $this->get(route('portal.payments.index'))->assertForbidden();
});

it('gives read-only auditors the audit log but not case editing', function () {
    $auditor = actingAsStaff('Read-Only Auditor');
    $this->get(route('portal.audit.index'))->assertOk();

    $case = makeCase();
    $this->put(route('portal.cases.update', $case), ['risk_level' => 'high'])->assertForbidden();
});

function makeCase(): \App\Models\CaseFile
{
    return \App\Models\CaseFile::query()->create([
        'case_number' => 'HCS-2026-MD-AA-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'pipeline_stage_id' => \App\Models\PipelineStage::query()->where('key', 'new_lead')->value('id'),
        'county' => 'Anne Arundel',
        'county_code' => 'AA',
        'state' => 'MD',
    ]);
}
