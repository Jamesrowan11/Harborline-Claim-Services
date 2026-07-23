<?php

use App\Models\Lead;

beforeEach(fn () => seedCore());

function inquiryPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Dana', 'last_name' => 'Testperson',
        'relationship_to_owner' => 'I am the former owner',
        'property_address' => '123 Fictional Harbor Lane',
        'property_county' => 'Anne Arundel', 'property_state' => 'MD',
        'email' => 'dana@example.test', 'preferred_contact_method' => 'email',
        'consent_to_contact' => '1', 'privacy_policy_agreed' => '1', 'electronic_consent' => '1',
    ], $overrides);
}

it('creates a lead with number, consents, and research tasks from the public form', function () {
    $this->post(route('site.check.store'), inquiryPayload())
        ->assertOk()
        ->assertSee('Submitted for Preliminary Review');

    $lead = Lead::query()->firstOrFail();
    expect($lead->lead_number)->toStartWith('L-'.now()->format('Y').'-')
        ->and($lead->consentRecords()->where('consent_type', 'privacy_policy')->where('granted', true)->exists())->toBeTrue()
        ->and($lead->consentRecords()->where('consent_type', 'sms')->where('granted', false)->exists())->toBeTrue()
        ->and($lead->tasks()->count())->toBe(3);
});

it('rejects submissions without required consents and never requires SMS consent', function () {
    $this->post(route('site.check.store'), inquiryPayload(['privacy_policy_agreed' => null]))
        ->assertSessionHasErrors('privacy_policy_agreed');

    $this->post(route('site.check.store'), inquiryPayload())->assertOk(); // no sms_consent → still fine
});

it('rejects honeypot submissions', function () {
    $this->post(route('site.check.store'), inquiryPayload(['website' => 'spambot']))
        ->assertSessionHasErrors('website');
});

it('flags duplicate leads by normalized property address', function () {
    $this->post(route('site.check.store'), inquiryPayload());
    $this->post(route('site.check.store'), inquiryPayload(['email' => 'other@example.test', 'property_address' => '123 Fictional Harbor Ln.']));

    $second = Lead::query()->orderByDesc('id')->first();
    expect($second->status)->toBe('screening')
        ->and($second->notes()->where('body', 'like', '%duplicate%')->exists())->toBeTrue();
});
