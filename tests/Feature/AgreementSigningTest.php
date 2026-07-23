<?php

use App\Models\Agreement;

beforeEach(fn () => seedCore());

it('lets a client sign a sent agreement and records signature metadata', function () {
    $case = createCase();
    $claimant = attachClaimant($case);
    $client = clientUser($claimant->id);

    $agreement = Agreement::query()->create([
        'case_id' => $case->id, 'claimant_id' => $claimant->id,
        'title' => 'Assistance Agreement', 'body_rendered' => 'Terms…', 'status' => 'sent',
    ]);

    $this->actingAs($client)
        ->post(route('client.cases.agreements.sign', [$case, $agreement]), [
            'signature_name' => 'Test Claimant', 'agree' => '1',
        ])->assertRedirect();

    $agreement->refresh();
    expect($agreement->status)->toBe('signed')
        ->and($agreement->signature_name)->toBe('Test Claimant')
        ->and($agreement->signed_at)->not->toBeNull()
        ->and($agreement->signature_ip)->not->toBeNull();
});

it('refuses signature on agreements that are not in sent status', function () {
    $case = createCase();
    $claimant = attachClaimant($case);
    $client = clientUser($claimant->id);

    $draft = Agreement::query()->create([
        'case_id' => $case->id, 'claimant_id' => $claimant->id,
        'title' => 'Draft', 'status' => 'draft',
    ]);

    $this->actingAs($client)
        ->post(route('client.cases.agreements.sign', [$case, $draft]), [
            'signature_name' => 'Test Claimant', 'agree' => '1',
        ])->assertStatus(422);
});
