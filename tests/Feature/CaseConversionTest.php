<?php

use App\Models\Lead;
use App\Services\CaseConversionService;

beforeEach(fn () => seedCore());

it('converts a lead into a case with property, claimant, and correct number', function () {
    $user = actingAsStaff('Case Manager');
    $lead = Lead::query()->create([
        'lead_number' => 'L-2026-000123', 'first_name' => 'Dana', 'last_name' => 'Testperson',
        'property_address' => '9 Sample Cove', 'property_county' => 'Howard', 'property_state' => 'MD',
        'email' => 'dana@example.test', 'phone' => '555-0102',
        'consent_to_contact' => true,
    ]);

    $case = app(CaseConversionService::class)->fromLead($lead, $user->id);

    expect($case->case_number)->toBe('HCS-'.now()->format('Y').'-MD-HO-000001')
        ->and($case->property->address_line1)->toBe('9 Sample Cove')
        ->and($case->claimants()->count())->toBe(1)
        ->and($lead->fresh()->status)->toBe('converted')
        ->and($lead->fresh()->case_id)->toBe($case->id);
});

it('exposes conversion through the portal and prevents double conversion', function () {
    actingAsStaff('Case Manager');
    $lead = Lead::query()->create([
        'lead_number' => 'L-2026-000124', 'first_name' => 'A', 'last_name' => 'B',
        'property_county' => 'Howard', 'property_state' => 'MD',
    ]);

    $this->post(route('portal.leads.convert', $lead))->assertRedirect();
    $this->post(route('portal.leads.convert', $lead))->assertStatus(422);
});
