<?php

use App\Models\PipelineStage;
use App\Models\StageTransition;
use App\Services\StageChangeService;

beforeEach(fn () => seedCore());

it('records transitions, closes cases on closed stages, and touches activity', function () {
    $user = actingAsStaff();
    $case = createCase();

    $closed = PipelineStage::query()->where('key', 'closed_no_surplus')->first();
    app(StageChangeService::class)->change($case, $closed, $user, 'No surplus found');

    $case->refresh();
    expect($case->pipeline_stage_id)->toBe($closed->id)
        ->and($case->closed_at)->not->toBeNull()
        ->and($case->last_activity_at)->not->toBeNull();

    $transition = StageTransition::query()->firstOrFail();
    expect($transition->to_stage_id)->toBe($closed->id)
        ->and($transition->user_id)->toBe($user->id)
        ->and($transition->reason)->toBe('No surplus found');
});

it('allows stage changes via the portal for authorized users only', function () {
    actingAsStaff('Case Manager');
    $case = createCase();
    $target = PipelineStage::query()->where('key', 'preliminary_screening')->first();

    $this->post(route('portal.cases.stage', $case), ['stage_id' => $target->id])->assertRedirect();
    expect($case->fresh()->pipeline_stage_id)->toBe($target->id);
});

it('maps internal stages to the approved client-facing labels', function () {
    $case = createCase([], 'claim_filed');
    expect($case->clientStatusLabel())->toBe('Claim Submitted');
});
