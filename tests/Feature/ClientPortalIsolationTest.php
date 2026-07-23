<?php

use App\Models\Document;
use App\Models\PortalMessage;

beforeEach(fn () => seedCore());

it('lets a client see only their own cases', function () {
    $caseA = createCase();
    $claimantA = attachClaimant($caseA);
    $caseB = createCase();
    attachClaimant($caseB, ['last_name' => 'Other']);

    $client = clientUser($claimantA->id);

    $this->actingAs($client)->get(route('client.cases.show', $caseA))->assertOk();
    $this->actingAs($client)->get(route('client.cases.show', $caseB))->assertForbidden();
});

it('never exposes staff-only notes or internal data to clients', function () {
    $case = createCase();
    $claimant = attachClaimant($case);
    $case->notes()->create(['body' => 'SECRET-SKIP-TRACE-NOTE', 'is_staff_only' => true]);

    $client = clientUser($claimant->id);
    $this->actingAs($client)->get(route('client.cases.show', $case))
        ->assertOk()
        ->assertDontSee('SECRET-SKIP-TRACE-NOTE');
});

it('hides non-client-visible and unapproved documents from clients', function () {
    $case = createCase();
    $claimant = attachClaimant($case);
    $client = clientUser($claimant->id);

    $hidden = Document::query()->create([
        'case_id' => $case->id, 'title' => 'Internal research PDF', 'category' => 'general',
        'original_filename' => 'x.pdf', 'path' => 'documents/x.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 10, 'status' => 'approved', 'client_visible' => false, 'virus_scan_status' => 'clean',
    ]);

    $this->actingAs($client)->get(route('client.cases.show', $case))->assertDontSee('Internal research PDF');
    $this->actingAs($client)->get(route('client.cases.documents.download', [$case, $hidden]))->assertForbidden();
});

it('prevents clients from messaging other cases', function () {
    $caseA = createCase();
    $claimantA = attachClaimant($caseA);
    $caseB = createCase();
    attachClaimant($caseB, ['last_name' => 'Other']);

    $client = clientUser($claimantA->id);
    $this->actingAs($client)
        ->post(route('client.cases.messages.store', $caseB), ['body' => 'hello'])
        ->assertForbidden();
    expect(PortalMessage::query()->count())->toBe(0);
});

it('blocks infected documents from download even for staff', function () {
    actingAsStaff();
    $case = createCase();
    $infected = Document::query()->create([
        'case_id' => $case->id, 'title' => 'Bad file', 'category' => 'general',
        'original_filename' => 'bad.pdf', 'path' => 'documents/bad.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 10, 'status' => 'approved', 'client_visible' => true, 'virus_scan_status' => 'infected',
    ]);

    $this->get(route('portal.documents.download', $infected))->assertForbidden();
});
