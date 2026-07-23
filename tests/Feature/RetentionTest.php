<?php

use App\Models\Lead;
use App\Models\LegalHold;
use App\Models\RetentionPolicy;

beforeEach(fn () => seedCore());

it('never deletes records under legal hold', function () {
    RetentionPolicy::query()->create([
        'record_type' => 'leads', 'retain_months' => 1, 'action_after' => 'delete', 'active' => true,
    ]);

    $old = Lead::query()->create([
        'lead_number' => 'L-2020-000001', 'first_name' => 'Old', 'last_name' => 'Lead', 'status' => 'closed',
    ]);
    Lead::query()->whereKey($old->id)->update(['updated_at' => now()->subYears(2)]);

    LegalHold::query()->create([
        'holdable_type' => $old->getMorphClass(), 'holdable_id' => $old->id,
        'reason' => 'Litigation hold',
    ]);

    $this->artisan('hcs:enforce-retention')->assertSuccessful();

    expect(Lead::withTrashed()->whereKey($old->id)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('deletes expired records when the policy is active and no hold exists', function () {
    RetentionPolicy::query()->create([
        'record_type' => 'leads', 'retain_months' => 1, 'action_after' => 'delete', 'active' => true,
    ]);

    $old = Lead::query()->create([
        'lead_number' => 'L-2020-000002', 'first_name' => 'Old', 'last_name' => 'Lead', 'status' => 'closed',
    ]);
    Lead::query()->whereKey($old->id)->update(['updated_at' => now()->subYears(2)]);

    $this->artisan('hcs:enforce-retention')->assertSuccessful();

    expect(Lead::query()->whereKey($old->id)->exists())->toBeFalse();
});

it('does nothing when policies are inactive', function () {
    RetentionPolicy::query()->create([
        'record_type' => 'leads', 'retain_months' => 1, 'action_after' => 'delete', 'active' => false,
    ]);
    $old = Lead::query()->create([
        'lead_number' => 'L-2020-000003', 'first_name' => 'Old', 'last_name' => 'Lead', 'status' => 'closed',
    ]);
    Lead::query()->whereKey($old->id)->update(['updated_at' => now()->subYears(2)]);

    $this->artisan('hcs:enforce-retention')->assertSuccessful();
    expect(Lead::query()->whereKey($old->id)->exists())->toBeTrue();
});
