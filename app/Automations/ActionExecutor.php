<?php

namespace App\Automations;

use App\Events\DomainEvent;
use App\Models\Automation;
use App\Models\AuditEvent;
use App\Models\CaseFile;
use App\Models\CaseTask;
use App\Models\Communication;
use App\Models\Deadline;
use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\Lead;
use App\Models\Note;
use App\Models\OptOut;
use App\Models\PipelineStage;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\OutreachGate;
use App\Services\StageChangeService;
use App\Services\TemplateRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Executes a single automation action. Outbound communications always pass
 * through the OutreachGate, which enforces template approval, opt-outs,
 * verification level, contact-frequency limits, and holds.
 */
class ActionExecutor
{
    public function __construct(
        private TemplateRenderer $renderer,
        private OutreachGate $outreachGate,
        private StageChangeService $stageChange,
    ) {
    }

    public function execute(Automation $automation, array $action, ?CaseFile $case, ?Lead $lead, DomainEvent $event): string
    {
        $params = $action['params'] ?? [];

        return match ($action['type']) {
            'create_task' => $this->createTask($params, $case, $lead),
            'assign_employee' => $this->assignEmployee($params, $case, $lead),
            'change_stage' => $this->changeStage($params, $case, $event),
            'send_internal_notification' => $this->internalNotification($params, $case, $lead),
            'send_email', 'send_sms' => $this->sendCommunication($action['type'] === 'send_email' ? 'email' : 'sms', $params, $case, $lead),
            'generate_letter' => $this->generateLetter($params, $case),
            'request_document' => $this->requestDocument($params, $case),
            'add_note' => $this->addNote($params, $case, $lead),
            'add_tag' => $this->addTag($params, $case),
            'schedule_follow_up' => $this->scheduleFollowUp($params, $case, $lead),
            'escalate_to_manager' => $this->escalate($params, $case),
            'require_compliance_review' => $this->flagCase($case, 'compliance_hold', 'Compliance review required by automation.'),
            'require_attorney_review' => $this->attorneyReview($case),
            'lock_workflow' => $this->flagCase($case, 'automation_paused', 'Workflow locked by automation.'),
            'add_calendar_item' => $this->addDeadline($params, $case),
            'trigger_webhook', 'call_external_api' => $this->webhook($params, $case, $event),
            'create_audit_record' => $this->auditRecord($params, $case, $automation),
            'close_duplicate' => $this->closeDuplicate($case),
            'reverify_source' => $this->reverifySource($case),
            'stop_outreach' => $this->flagCase($case, 'outreach_approved', 'Outreach stopped by automation.', false),
            'send_portal_notification' => $this->portalNotification($params, $case),
            default => throw new \InvalidArgumentException("Unknown automation action: {$action['type']}"),
        };
    }

    private function createTask(array $params, ?CaseFile $case, ?Lead $lead): string
    {
        CaseTask::query()->create([
            'case_id' => $case?->id,
            'lead_id' => $lead?->id,
            'title' => $params['title'] ?? 'Automation task',
            'description' => $params['description'] ?? null,
            'priority' => $params['priority'] ?? 'normal',
            'assigned_to' => $this->resolveUser($params['assignee'] ?? null)?->id ?? $case?->assigned_to,
            'due_at' => isset($params['due_in_days']) ? now()->addDays((int) $params['due_in_days']) : null,
        ]);

        return 'Task created';
    }

    private function assignEmployee(array $params, ?CaseFile $case, ?Lead $lead): string
    {
        $user = $this->resolveUser($params['assignee'] ?? null);
        if (! $user) {
            return 'Assignee not found; skipped';
        }
        $case?->update(['assigned_to' => $user->id]);
        $lead?->update(['assigned_to' => $user->id]);

        return "Assigned to {$user->name}";
    }

    private function changeStage(array $params, ?CaseFile $case, DomainEvent $event): string
    {
        if (! $case) {
            return 'No case; skipped';
        }
        $stage = PipelineStage::query()->where('key', $params['stage'] ?? '')->first();
        if (! $stage) {
            return 'Stage not found; skipped';
        }
        $this->stageChange->change($case, $stage, null, 'Automation', chainDepth: ($event->context['chain_depth'] ?? 0) + 1);

        return "Stage changed to {$stage->name}";
    }

    private function internalNotification(array $params, ?CaseFile $case, ?Lead $lead): string
    {
        $user = $this->resolveUser($params['user'] ?? null) ?? $case?->assignee ?? $lead?->assignee;
        if (! $user) {
            return 'No recipient; skipped';
        }
        $user->notify(new \App\Notifications\InternalAlertNotification(
            $params['message'] ?? 'Automation notification',
            $case?->case_number ?? $lead?->lead_number,
        ));

        return "Notified {$user->name}";
    }

    private function sendCommunication(string $channel, array $params, ?CaseFile $case, ?Lead $lead): string
    {
        $template = DocumentTemplate::query()->where('key', $params['template'] ?? '')->first();
        $claimant = $case?->claimants()->wherePivot('role', 'primary')->first();

        $gate = $this->outreachGate->check($channel, $template, $case, $lead, $claimant);
        if ($gate !== true) {
            return "BLOCKED: $gate";
        }

        $rendered = $this->renderer->render($template, $case, $claimant);
        $recipient = $channel === 'email'
            ? ($claimant?->emailAddresses()->where('opted_out', false)->value('email') ?? $lead?->email)
            : ($claimant?->phoneNumbers()->where('sms_capable', true)->value('number') ?? $lead?->phone);

        $communication = Communication::query()->create([
            'case_id' => $case?->id,
            'lead_id' => $lead?->id,
            'claimant_id' => $claimant?->id,
            'channel' => $channel,
            'direction' => 'outbound',
            'recipient' => $recipient,
            'subject' => $rendered['subject'],
            'body_rendered' => $rendered['body'],
            'document_template_id' => $template->id,
            'template_version' => $template->version,
            'status' => 'queued',
            'delivery_method' => $channel,
        ]);

        \App\Jobs\SendCommunicationJob::dispatch($communication->id);

        return ucfirst($channel).' queued';
    }

    private function generateLetter(array $params, ?CaseFile $case): string
    {
        $template = DocumentTemplate::query()->where('key', $params['template'] ?? '')->first();
        if (! $template || ! $case) {
            return 'Template or case missing; skipped';
        }
        if (! $template->isApproved()) {
            return 'BLOCKED: letter template is not approved';
        }
        $claimant = $case->claimants()->wherePivot('role', 'primary')->first();
        $rendered = $this->renderer->render($template, $case, $claimant);
        Communication::query()->create([
            'case_id' => $case->id,
            'claimant_id' => $claimant?->id,
            'channel' => 'letter',
            'direction' => 'outbound',
            'subject' => $rendered['subject'],
            'body_rendered' => $rendered['body'],
            'document_template_id' => $template->id,
            'template_version' => $template->version,
            'status' => 'draft',
        ]);

        return 'Letter drafted for review';
    }

    private function requestDocument(array $params, ?CaseFile $case): string
    {
        if (! $case) {
            return 'No case; skipped';
        }
        DocumentRequest::query()->create([
            'case_id' => $case->id,
            'claimant_id' => $case->claimants()->wherePivot('role', 'primary')->first()?->id,
            'name' => $params['name'] ?? 'Requested document',
            'instructions' => $params['instructions'] ?? null,
            'due_at' => isset($params['due_in_days']) ? now()->addDays((int) $params['due_in_days']) : null,
        ]);

        return 'Document requested';
    }

    private function addNote(array $params, ?CaseFile $case, ?Lead $lead): string
    {
        $target = $case ?? $lead;
        if (! $target) {
            return 'No target; skipped';
        }
        $target->notes()->create(['body' => $params['body'] ?? 'Automation note', 'is_staff_only' => true]);

        return 'Note added';
    }

    private function addTag(array $params, ?CaseFile $case): string
    {
        if (! $case) {
            return 'No case; skipped';
        }
        $custom = $case->custom_fields ?? [];
        $custom['tags'] = array_values(array_unique(array_merge($custom['tags'] ?? [], [(string) ($params['tag'] ?? '')])));
        $case->update(['custom_fields' => $custom]);

        return 'Tag added';
    }

    private function scheduleFollowUp(array $params, ?CaseFile $case, ?Lead $lead): string
    {
        return $this->createTask([
            'title' => $params['title'] ?? 'Follow up',
            'due_in_days' => $params['due_in_days'] ?? 3,
        ], $case, $lead);
    }

    private function escalate(array $params, ?CaseFile $case): string
    {
        $manager = $case?->caseManager;
        if (! $manager) {
            return 'No case manager; skipped';
        }
        $manager->notify(new \App\Notifications\InternalAlertNotification(
            $params['message'] ?? 'Escalation from automation', $case->case_number));

        return "Escalated to {$manager->name}";
    }

    private function attorneyReview(?CaseFile $case): string
    {
        if (! $case) {
            return 'No case; skipped';
        }
        $stage = PipelineStage::query()->where('key', 'attorney_review_required')->first();
        if ($stage) {
            $this->stageChange->change($case, $stage, null, 'Attorney review required by automation', chainDepth: 99);
        }

        return 'Attorney review required';
    }

    private function flagCase(?CaseFile $case, string $field, string $message, bool $value = true): string
    {
        if (! $case) {
            return 'No case; skipped';
        }
        $case->update([$field => $value]);

        return $message;
    }

    private function addDeadline(array $params, ?CaseFile $case): string
    {
        if (! $case) {
            return 'No case; skipped';
        }
        Deadline::query()->create([
            'case_id' => $case->id,
            'name' => $params['name'] ?? 'Calendar item',
            'due_at' => isset($params['due_in_days']) ? now()->addDays((int) $params['due_in_days']) : now()->addWeek(),
            'source' => 'automation',
        ]);

        return 'Calendar item added';
    }

    private function webhook(array $params, ?CaseFile $case, DomainEvent $event): string
    {
        $endpoint = WebhookEndpoint::query()->where('name', $params['endpoint'] ?? '')->where('active', true)->first();
        if (! $endpoint) {
            return 'Webhook endpoint not found or inactive; skipped';
        }
        $payload = [
            'event' => $event->key(),
            'case_number' => $case?->case_number,
            'timestamp' => now()->toIso8601String(),
            'data' => $params['data'] ?? [],
        ];
        $signature = hash_hmac('sha256', json_encode($payload), (string) $endpoint->secret);
        Http::timeout(10)->withHeaders(['X-Signature' => $signature])->post($endpoint->url, $payload);

        return 'Webhook delivered';
    }

    private function auditRecord(array $params, ?CaseFile $case, Automation $automation): string
    {
        AuditEvent::record('automation_note', $case, [], ['message' => $params['message'] ?? '', 'automation' => $automation->name]);

        return 'Audit record created';
    }

    private function closeDuplicate(?CaseFile $case): string
    {
        if (! $case) {
            return 'No case; skipped';
        }
        $stage = PipelineStage::query()->where('key', 'closed_duplicate')->first();
        if ($stage) {
            $this->stageChange->change($case, $stage, null, 'Closed as duplicate by automation', chainDepth: 99);
        }

        return 'Closed as duplicate';
    }

    private function reverifySource(?CaseFile $case): string
    {
        if (! $case) {
            return 'No case; skipped';
        }
        $case->sourceRecords()->where('status', 'current')->update(['status' => 'stale']);

        return $this->createTask(['title' => 'Re-verify source records', 'due_in_days' => 3], $case, null);
    }

    private function portalNotification(array $params, ?CaseFile $case): string
    {
        $clientUser = $case?->claimants()->wherePivot('role', 'primary')->first()?->user;
        if (! $clientUser) {
            return 'No portal user; skipped';
        }
        $clientUser->notify(new \App\Notifications\ClientPortalNotification(
            $params['message'] ?? __('There is an update on your case.'), $case->case_number));

        return 'Portal notification sent';
    }

    private function resolveUser(?string $identifier): ?User
    {
        if (! $identifier) {
            return null;
        }

        return User::query()->where('email', $identifier)->orWhere('name', $identifier)->first();
    }
}
