<?php

use App\Automations\AutomationEngine;
use App\Events\StageChanged;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\CaseTask;
use App\Services\Settings;

beforeEach(fn () => seedCore());

function makeAutomation(array $overrides = []): Automation
{
    return Automation::query()->create(array_merge([
        'name' => 'Test automation',
        'trigger_event' => 'case.stage_changed',
        'conditions' => [],
        'actions' => [['type' => 'create_task', 'params' => ['title' => 'Automation-created task']]],
        'mode' => 'active',
    ], $overrides));
}

function fireStageChanged($case, array $context = []): void
{
    app(AutomationEngine::class)->handle(new StageChanged($case, null, array_merge(['idempotency_suffix' => uniqid()], $context)));
}

it('executes active automation actions', function () {
    $case = createCase();
    makeAutomation();

    fireStageChanged($case);

    expect(CaseTask::query()->where('title', 'Automation-created task')->exists())->toBeTrue()
        ->and(AutomationRun::query()->where('status', 'success')->count())->toBe(1);
});

it('never runs draft automations', function () {
    $case = createCase();
    makeAutomation(['mode' => 'draft']);

    fireStageChanged($case);

    expect(CaseTask::query()->count())->toBe(0)->and(AutomationRun::query()->count())->toBe(0);
});

it('logs but does not act in test mode', function () {
    $case = createCase();
    makeAutomation(['mode' => 'test']);

    fireStageChanged($case);

    expect(CaseTask::query()->count())->toBe(0)
        ->and(AutomationRun::query()->where('status', 'test')->count())->toBe(1);
});

it('queues pending approval in approval mode', function () {
    $case = createCase();
    makeAutomation(['mode' => 'approval']);

    fireStageChanged($case);

    expect(AutomationRun::query()->where('status', 'pending_approval')->count())->toBe(1)
        ->and(CaseTask::query()->count())->toBe(0);
});

it('refuses to run unapproved sensitive automations', function () {
    $case = createCase();
    makeAutomation(['is_sensitive' => true, 'approved_at' => null]);

    fireStageChanged($case);

    expect(AutomationRun::query()->count())->toBe(0);
});

it('evaluates conditions before acting', function () {
    $case = createCase(['county' => 'Howard']);
    makeAutomation(['conditions' => [['field' => 'county', 'operator' => 'equals', 'value' => 'Anne Arundel']]]);

    fireStageChanged($case);
    expect(CaseTask::query()->count())->toBe(0);

    fireStageChanged(createCase(['county' => 'Anne Arundel']));
    expect(CaseTask::query()->count())->toBe(1);
});

it('is idempotent for the same suffix', function () {
    $case = createCase();
    makeAutomation();

    app(AutomationEngine::class)->handle(new StageChanged($case, null, ['idempotency_suffix' => 'same']));
    app(AutomationEngine::class)->handle(new StageChanged($case, null, ['idempotency_suffix' => 'same']));

    expect(AutomationRun::query()->count())->toBe(1);
});

it('skips paused cases and blocks held cases', function () {
    makeAutomation();

    fireStageChanged(createCase(['automation_paused' => true]));
    expect(AutomationRun::query()->where('status', 'skipped')->count())->toBe(1);

    fireStageChanged(createCase(['legal_hold' => true]));
    expect(AutomationRun::query()->where('status', 'blocked')->count())->toBe(1)
        ->and(CaseTask::query()->count())->toBe(0);
});

it('enforces per-case daily rate limits', function () {
    $case = createCase();
    makeAutomation(['max_runs_per_case_per_day' => 2]);

    foreach (range(1, 3) as $i) {
        fireStageChanged($case);
    }

    expect(AutomationRun::query()->where('status', 'success')->count())->toBe(2)
        ->and(AutomationRun::query()->where('status', 'blocked')->count())->toBe(1);
});

it('stops everything under the global emergency stop', function () {
    actingAsStaff();
    Settings::set('automation_global_stop', true);
    makeAutomation();

    fireStageChanged(createCase());

    expect(AutomationRun::query()->count())->toBe(0);
});

it('prevents infinite loops via chain depth', function () {
    $case = createCase();
    makeAutomation();

    fireStageChanged($case, ['chain_depth' => 99]);

    expect(AutomationRun::query()->where('status', 'blocked')->count())->toBe(1)
        ->and(CaseTask::query()->count())->toBe(0);
});
