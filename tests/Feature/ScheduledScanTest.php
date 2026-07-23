<?php

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\CaseTask;
use App\Models\Deadline;
use App\Services\ScheduledScanService;

beforeEach(fn () => seedCore());

it('fires deadline-approaching events that active automations consume', function () {
    $case = createCase();
    Deadline::query()->create(['case_id' => $case->id, 'name' => 'Filing follow-up', 'due_at' => now()->addDays(3)]);

    Automation::query()->create([
        'name' => 'Deadline alert', 'trigger_event' => 'deadline.approaching',
        'conditions' => [], 'mode' => 'active',
        'actions' => [['type' => 'create_task', 'params' => ['title' => 'Deadline follow-up task']]],
    ]);

    app(ScheduledScanService::class)->run();

    expect(CaseTask::query()->where('title', 'Deadline follow-up task')->exists())->toBeTrue();
});

it('is idempotent within a day for repeated scans', function () {
    $case = createCase();
    Deadline::query()->create(['case_id' => $case->id, 'name' => 'Filing follow-up', 'due_at' => now()->addDays(3)]);
    Automation::query()->create([
        'name' => 'Deadline alert', 'trigger_event' => 'deadline.approaching',
        'conditions' => [], 'mode' => 'active',
        'actions' => [['type' => 'create_task', 'params' => ['title' => 'Deadline follow-up task']]],
    ]);

    app(ScheduledScanService::class)->run();
    app(ScheduledScanService::class)->run();

    expect(AutomationRun::query()->count())->toBe(1)
        ->and(CaseTask::query()->where('title', 'Deadline follow-up task')->count())->toBe(1);
});

it('marks stale sources and fires the stale event', function () {
    $case = createCase();
    $source = $case->sourceRecords()->create([
        'source_type' => 'court_docket', 'title' => 'Docket pull',
        'status' => 'current', 'stale_after' => now()->subDay(),
    ]);

    app(ScheduledScanService::class)->run();

    expect($source->fresh()->status)->toBe('stale');
});
