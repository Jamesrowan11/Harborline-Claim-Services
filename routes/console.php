<?php

use Illuminate\Support\Facades\Schedule;

// Daily automation-event scans (deadlines, overdue tasks, stale sources,
// inactivity, expired verifications).
Schedule::command('hcs:daily-scans')->dailyAt('06:00');

// Retention enforcement (skips anything under legal hold).
Schedule::command('hcs:enforce-retention')->dailyAt('02:30');

// Queue hygiene when using the database driver.
Schedule::command('queue:prune-failed --hours=168')->daily();

// Recurring schedule.tick event for admin-configured scheduled automations.
Schedule::call(function () {
    event(new \App\Events\ScheduledTick(null, null, [
        'idempotency_suffix' => 'tick-'.now()->format('Y-m-d-H'),
    ]));
})->hourly()->name('automation-schedule-tick');
