<?php

use App\Models\User;
use Database\Seeders\PipelineStageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Seed roles + stages, common to nearly every feature test. */
function seedCore(): void
{
    test()->seed(RolePermissionSeeder::class);
    test()->seed(PipelineStageSeeder::class);
}

function staffUser(string $role = 'Company Administrator', array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'user_type' => 'staff',
        'mfa_enabled_at' => now(), // enrolled; tests set session flag below
    ], $attributes));
    $user->assignRole($role);

    return $user;
}

function actingAsStaff(string $role = 'Company Administrator', array $attributes = []): User
{
    $user = staffUser($role, $attributes);
    test()->actingAs($user)->withSession(['mfa_passed' => true]);

    return $user;
}

function clientUser(?int $claimantId = null): User
{
    $user = User::factory()->create(['user_type' => 'client', 'claimant_id' => $claimantId]);
    $user->assignRole('Client');

    return $user;
}

function createCase(array $attributes = [], string $stage = 'new_lead'): \App\Models\CaseFile
{
    return \App\Models\CaseFile::query()->create(array_merge([
        'case_number' => 'HCS-2026-MD-AA-'.str_pad((string) (\App\Models\CaseFile::query()->count() + 1), 6, '0', STR_PAD_LEFT),
        'pipeline_stage_id' => \App\Models\PipelineStage::query()->where('key', $stage)->value('id'),
        'county' => 'Anne Arundel',
        'county_code' => 'AA',
        'state' => 'MD',
    ], $attributes));
}

function attachClaimant(\App\Models\CaseFile $case, array $attributes = []): \App\Models\Claimant
{
    $claimant = \App\Models\Claimant::query()->create(array_merge([
        'type' => 'individual', 'first_name' => 'Test', 'last_name' => 'Claimant',
    ], $attributes));
    $case->claimants()->attach($claimant->id, ['role' => 'primary']);

    return $claimant;
}
