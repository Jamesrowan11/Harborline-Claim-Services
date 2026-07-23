<?php

use App\Models\DocumentTemplate;
use App\Models\Lead;
use App\Models\OptOut;
use App\Services\OutreachGate;

beforeEach(function () {
    seedCore();
    $this->seed(Database\Seeders\DocumentTemplateSeeder::class);
});

function approvedTemplate(string $key = 'inquiry_acknowledgment'): DocumentTemplate
{
    $template = DocumentTemplate::query()->where('key', $key)->firstOrFail();
    $template->update(['approval_status' => 'approved', 'approved_at' => now()]);

    return $template;
}

function outreachCase(): array
{
    $case = createCase(['verification_level' => 'source_confirmed', 'outreach_approved' => true]);
    $claimant = attachClaimant($case);
    $claimant->emailAddresses()->create(['email' => 'claimant@example.test']);
    $claimant->phoneNumbers()->create(['number' => '555-0101', 'sms_capable' => true]);

    return [$case, $claimant];
}

it('blocks unapproved templates', function () {
    [$case, $claimant] = outreachCase();
    $template = DocumentTemplate::query()->where('key', 'inquiry_acknowledgment')->first(); // still draft

    $result = app(OutreachGate::class)->check('email', $template, $case, null, $claimant);
    expect($result)->toContain('not approved');
});

it('blocks recipients who opted out', function () {
    [$case, $claimant] = outreachCase();
    OptOut::query()->create([
        'channel' => 'email', 'value' => 'claimant@example.test',
        'optoutable_type' => $claimant->getMorphClass(), 'optoutable_id' => $claimant->id,
        'occurred_at' => now(),
    ]);

    $result = app(OutreachGate::class)->check('email', approvedTemplate(), $case, null, $claimant);
    expect($result)->toContain('opted out');
});

it('blocks SMS without separate SMS consent and when SMS is disabled', function () {
    [$case, $claimant] = outreachCase();
    $template = approvedTemplate();

    config(['security.sms.enabled' => false]);
    expect(app(OutreachGate::class)->check('sms', $template, $case, null, $claimant))->toContain('disabled');

    config(['security.sms.enabled' => true]);
    expect(app(OutreachGate::class)->check('sms', $template, $case, null, $claimant))->toContain('SMS consent');

    $claimant->consentRecords()->create([
        'consent_type' => 'sms', 'granted' => true, 'source' => 'portal', 'occurred_at' => now(),
    ]);
    expect(app(OutreachGate::class)->check('sms', $template, $case, null, $claimant))->toBeTrue();
});

it('blocks cases below the verification threshold, without outreach approval, or on hold', function () {
    $template = approvedTemplate();

    [$case, $claimant] = outreachCase();
    $case->update(['verification_level' => 'none']);
    expect(app(OutreachGate::class)->check('email', $template, $case, null, $claimant))->toContain('verification level');

    $case->update(['verification_level' => 'source_confirmed', 'outreach_approved' => false]);
    expect(app(OutreachGate::class)->check('email', $template, $case, null, $claimant))->toContain('not been approved');

    $case->update(['outreach_approved' => true, 'compliance_hold' => true]);
    expect(app(OutreachGate::class)->check('email', $template, $case, null, $claimant))->toContain('hold');
});

it('enforces contact-frequency limits', function () {
    [$case, $claimant] = outreachCase();
    $template = approvedTemplate();

    foreach (range(1, 2) as $i) {
        \App\Models\Communication::query()->create([
            'case_id' => $case->id, 'channel' => 'email', 'direction' => 'outbound',
            'recipient' => 'claimant@example.test', 'status' => 'sent', 'body_rendered' => 'x',
        ]);
    }

    expect(app(OutreachGate::class)->check('email', $template, $case, null, $claimant))->toContain('frequency');
});

it('blocks leads without consent to contact', function () {
    $lead = Lead::query()->create([
        'lead_number' => 'L-2026-000900', 'first_name' => 'No', 'last_name' => 'Consent',
        'email' => 'noconsent@example.test', 'consent_to_contact' => false,
    ]);

    expect(app(OutreachGate::class)->check('email', approvedTemplate(), null, $lead, null))->toContain('consented');
});
