<?php

namespace App\Automations;

use App\Events\DomainEvent;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\CaseFile;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

/**
 * Automation engine with layered safety controls:
 *
 *  - global emergency stop (config/settings flag)
 *  - per-automation modes: draft (never runs), test (logs only), approval
 *    (queues a pending-approval run), active
 *  - sensitive automations (legal / financial / SMS / client outreach) can
 *    never run without administrator approval
 *  - per-case automation pause
 *  - rate limiting per automation and per case
 *  - idempotency keys prevent double-execution
 *  - loop prevention via a chain-depth counter carried in context
 */
class AutomationEngine
{
    public const MAX_CHAIN_DEPTH = 3;

    public function __construct(private ActionExecutor $executor)
    {
    }

    public function handle(DomainEvent $event): void
    {
        if (config('security.automation_global_stop') || \App\Services\Settings::get('automation_global_stop', false)) {
            return;
        }

        $automations = Automation::query()
            ->where('trigger_event', $event->key())
            ->whereNull('deleted_at')
            ->get();

        foreach ($automations as $automation) {
            $this->run($automation, $event);
        }
    }

    public function run(Automation $automation, DomainEvent $event): ?AutomationRun
    {
        $case = $event->case;
        $lead = $event->lead;

        $idempotencyKey = sha1(implode('|', [
            $automation->id,
            $event->key(),
            $case?->id ?? 'null',
            $lead?->id ?? 'null',
            $event->context['idempotency_suffix'] ?? now()->format('YmdHi'),
        ]));

        if (AutomationRun::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return null;
        }

        $skip = fn (string $status, string $message) => AutomationRun::query()->create([
            'automation_id' => $automation->id,
            'case_id' => $case?->id,
            'lead_id' => $lead?->id,
            'trigger_event' => $event->key(),
            'status' => $status,
            'idempotency_key' => $idempotencyKey,
            'message' => $message,
            'context' => $event->context,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        if (! $automation->isRunnable()) {
            return null; // draft/paused/unapproved-sensitive automations are silently inert
        }

        if (($event->context['chain_depth'] ?? 0) >= self::MAX_CHAIN_DEPTH) {
            return $skip('blocked', 'Loop prevention: maximum automation chain depth reached.');
        }

        if ($case && $case->automation_paused) {
            return $skip('skipped', 'Automations are paused for this case.');
        }

        if ($case && ($case->compliance_hold || $case->legal_hold)) {
            return $skip('blocked', 'Case is on compliance or legal hold.');
        }

        // Rate limits
        $hourly = AutomationRun::query()
            ->where('automation_id', $automation->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($hourly >= $automation->max_runs_per_hour) {
            return $skip('blocked', 'Hourly rate limit reached for this automation.');
        }

        if ($case) {
            $daily = AutomationRun::query()
                ->where('automation_id', $automation->id)
                ->where('case_id', $case->id)
                ->where('created_at', '>=', now()->subDay())
                ->count();
            if ($daily >= $automation->max_runs_per_case_per_day) {
                return $skip('blocked', 'Per-case daily rate limit reached.');
            }
        }

        if (! ConditionEvaluator::passes($automation->conditions ?? [], $case, $lead, $event->context)) {
            return null; // conditions not met is not an error and not worth a log row
        }

        if ($automation->mode === 'approval') {
            return $skip('pending_approval', 'Awaiting manual approval before actions execute.');
        }

        $run = AutomationRun::query()->create([
            'automation_id' => $automation->id,
            'case_id' => $case?->id,
            'lead_id' => $lead?->id,
            'trigger_event' => $event->key(),
            'status' => $automation->mode === 'test' ? 'test' : 'success',
            'idempotency_key' => $idempotencyKey,
            'context' => $event->context,
            'started_at' => now(),
        ]);

        if ($automation->mode === 'test') {
            $run->update([
                'message' => 'Test mode: conditions matched; actions were NOT executed. Actions: '
                    .collect($automation->actions)->pluck('type')->implode(', '),
                'finished_at' => now(),
            ]);

            return $run;
        }

        try {
            $messages = [];
            foreach ($automation->actions as $action) {
                $messages[] = $this->executor->execute($automation, $action, $case, $lead, $event);
            }
            $run->update(['message' => implode(' | ', array_filter($messages)), 'finished_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Automation failed', ['automation' => $automation->id, 'error' => $e->getMessage()]);
            $run->update(['status' => 'failed', 'message' => $e->getMessage(), 'finished_at' => now()]);
        }

        return $run;
    }
}
