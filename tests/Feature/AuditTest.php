<?php

use App\Models\AuditEvent;
use App\Models\Claimant;

beforeEach(fn () => seedCore());

it('records create, update, and delete audit events with the acting user', function () {
    $user = actingAsStaff();
    $case = createCase();

    $case->update(['risk_level' => 'high']);
    $case->delete();

    $events = AuditEvent::query()->where('auditable_type', $case->getMorphClass())->pluck('event');
    expect($events)->toContain('created')->toContain('updated')->toContain('deleted');

    $update = AuditEvent::query()->where('event', 'updated')->where('auditable_type', $case->getMorphClass())->first();
    expect($update->new_values['risk_level'])->toBe('high')
        ->and($update->user_id)->toBe($user->id);
});

it('masks sensitive attributes in audit payloads', function () {
    actingAsStaff();
    $claimant = Claimant::query()->create([
        'type' => 'individual', 'first_name' => 'Jo', 'last_name' => 'Doe',
        'ssn_last_four' => '1234', 'date_of_birth' => '1950-01-01',
    ]);

    $event = AuditEvent::query()->where('auditable_type', $claimant->getMorphClass())->where('event', 'created')->first();
    expect($event->new_values['ssn_last_four'])->toBe('[redacted]')
        ->and($event->new_values['date_of_birth'])->toBe('[redacted]');
});

it('records document downloads', function () {
    actingAsStaff();
    $case = createCase();
    \Illuminate\Support\Facades\Storage::fake('private');
    \Illuminate\Support\Facades\Storage::disk('private')->put('documents/test.pdf', 'content');
    $document = \App\Models\Document::query()->create([
        'case_id' => $case->id, 'title' => 'Test', 'category' => 'general',
        'original_filename' => 'test.pdf', 'path' => 'documents/test.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 7, 'status' => 'approved', 'virus_scan_status' => 'clean',
    ]);

    $this->get(route('portal.documents.download', $document))->assertOk();
    expect(AuditEvent::query()->where('event', 'document_downloaded')->exists())->toBeTrue();
});
