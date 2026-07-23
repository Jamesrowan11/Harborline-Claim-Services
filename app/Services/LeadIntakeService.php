<?php

namespace App\Services;

use App\Events\LeadCreated;
use App\Models\CaseTask;
use App\Models\ConsentRecord;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles a new public inquiry: creates the lead with a unique lead number,
 * records consents, checks duplicates, applies configurable assignment rules,
 * creates preliminary research tasks, and queues the confirmation email.
 */
class LeadIntakeService
{
    public function __construct(private DuplicateDetectionService $duplicates)
    {
    }

    public function create(array $data, ?string $ip = null, string $source = 'website'): Lead
    {
        $lead = DB::transaction(function () use ($data, $ip, $source) {
            $lead = Lead::query()->create(array_merge($data, [
                'lead_number' => $this->nextLeadNumber(),
                'submitted_ip' => $ip,
                'source' => $source,
            ]));

            foreach ([
                'contact' => $lead->consent_to_contact,
                'privacy_policy' => $lead->privacy_policy_agreed,
                'electronic_communications' => $lead->electronic_consent,
                'sms' => $lead->sms_consent,
            ] as $type => $granted) {
                ConsentRecord::query()->create([
                    'consentable_type' => $lead->getMorphClass(),
                    'consentable_id' => $lead->id,
                    'consent_type' => $type,
                    'granted' => (bool) $granted,
                    'source' => 'web_form',
                    'text_version' => (string) Settings::get("consent_text_version.$type", 'v1'),
                    'ip_address' => $ip,
                    'occurred_at' => now(),
                ]);
            }

            return $lead;
        });

        // Duplicate check
        $duplicates = $this->duplicates->forLead($lead);
        if ($duplicates->isNotEmpty()) {
            $lead->update(['status' => 'screening']);
            $lead->notes()->create([
                'body' => 'Possible duplicates found: '.$duplicates->map(
                    fn ($m) => ($m['record']->lead_number ?? $m['record']->case_number).' ('.$m['reason'].')'
                )->implode('; '),
                'is_staff_only' => true,
            ]);
        }

        // Configurable assignment rules: settings key assignment_rules = [{county, state, assignee_email}]
        $this->applyAssignmentRules($lead);

        // Preliminary research tasks
        foreach ([
            'Confirm property and sale details from public records',
            'Check for existing or duplicate files',
            'Identify possible funds holder',
        ] as $title) {
            CaseTask::query()->create([
                'lead_id' => $lead->id,
                'title' => $title,
                'assigned_to' => $lead->assigned_to,
                'due_at' => now()->addDays(3),
            ]);
        }

        if ($lead->email && $lead->consent_to_contact) {
            rescue(fn () => \Illuminate\Support\Facades\Notification::route('mail', $lead->email)
                ->notify(new \App\Notifications\InquiryReceivedNotification($lead)), report: false);
        }

        // Notify assigned employee (or all Company Administrators when unassigned)
        $recipients = $lead->assigned_to
            ? User::query()->whereKey($lead->assigned_to)->get()
            : User::role('Company Administrator')->get();
        foreach ($recipients as $recipient) {
            rescue(fn () => $recipient->notify(new \App\Notifications\InternalAlertNotification(
                __('New inquiry received: :number', ['number' => $lead->lead_number]), $lead->lead_number)), report: false);
        }

        event(new LeadCreated(null, $lead, ['county' => $lead->property_county, 'state' => $lead->property_state]));

        return $lead;
    }

    public function applyAssignmentRules(Lead $lead): void
    {
        $rules = (array) Settings::get('assignment_rules', []);
        foreach ($rules as $rule) {
            $countyMatches = blank($rule['county'] ?? null) || strcasecmp($rule['county'], (string) $lead->property_county) === 0;
            $stateMatches = blank($rule['state'] ?? null) || strcasecmp($rule['state'], (string) $lead->property_state) === 0;
            if ($countyMatches && $stateMatches) {
                $user = User::query()->where('email', $rule['assignee_email'] ?? '')->first();
                if ($user) {
                    $lead->update(['assigned_to' => $user->id]);

                    return;
                }
            }
        }
    }

    private function nextLeadNumber(): string
    {
        $prefix = 'L-'.now()->format('Y').'-';
        $last = Lead::query()->where('lead_number', 'like', $prefix.'%')
            ->orderByDesc('id')->value('lead_number');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
