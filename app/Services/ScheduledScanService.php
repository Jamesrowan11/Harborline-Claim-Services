<?php

namespace App\Services;

use App\Events\CaseInactive;
use App\Events\DeadlineApproaching;
use App\Events\SourceRecordStale;
use App\Events\TaskOverdue;
use App\Events\VerificationExpired;
use App\Models\CaseFile;
use App\Models\CaseTask;
use App\Models\Deadline;
use App\Models\SourceRecord;
use App\Models\SurplusRecord;

/**
 * Daily scans that convert time-based situations into domain events so the
 * automation engine (and its safety gates) can respond. Each event carries an
 * idempotency suffix of the calendar date so a scan re-run cannot double-fire.
 */
class ScheduledScanService
{
    public function run(): array
    {
        $today = now()->format('Y-m-d');
        $counts = ['deadlines' => 0, 'tasks' => 0, 'sources' => 0, 'inactive' => 0, 'verifications' => 0];

        Deadline::query()->with('case')->whereNull('met_at')
            ->whereBetween('due_at', [now(), now()->addDays(7)])
            ->each(function (Deadline $deadline) use ($today, &$counts) {
                $counts['deadlines']++;
                event(new DeadlineApproaching($deadline->case, null, [
                    'deadline_id' => $deadline->id,
                    'deadline_name' => $deadline->name,
                    'idempotency_suffix' => "deadline-{$deadline->id}-$today",
                ]));
            });

        CaseTask::query()->with('case')->where('status', '!=', 'done')
            ->where('due_at', '<', now())
            ->each(function (CaseTask $task) use ($today, &$counts) {
                $counts['tasks']++;
                event(new TaskOverdue($task->case, $task->lead, [
                    'task_id' => $task->id,
                    'idempotency_suffix' => "task-{$task->id}-$today",
                ]));
            });

        SourceRecord::query()->with('case')->where('status', 'current')
            ->where('stale_after', '<', now())
            ->each(function (SourceRecord $source) use ($today, &$counts) {
                $counts['sources']++;
                $source->update(['status' => 'stale']);
                event(new SourceRecordStale($source->case, null, [
                    'source_id' => $source->id,
                    'idempotency_suffix' => "source-{$source->id}-$today",
                ]));
            });

        $inactivityDays = (int) Settings::get('inactivity_alert_days', 21);
        CaseFile::query()->with('stage')
            ->whereHas('stage', fn ($q) => $q->where('is_closed', false))
            ->where('last_activity_at', '<', now()->subDays($inactivityDays))
            ->each(function (CaseFile $case) use ($today, &$counts) {
                $counts['inactive']++;
                event(new CaseInactive($case, null, [
                    'idempotency_suffix' => "inactive-{$case->id}-$today",
                ]));
            });

        SurplusRecord::query()->with('case')->where('status', 'verified')
            ->where('verification_expires_at', '<', now())
            ->each(function (SurplusRecord $record) use ($today, &$counts) {
                $counts['verifications']++;
                event(new VerificationExpired($record->case, null, [
                    'surplus_record_id' => $record->id,
                    'idempotency_suffix' => "verification-{$record->id}-$today",
                ]));
            });

        return $counts;
    }
}
