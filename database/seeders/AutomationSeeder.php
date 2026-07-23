<?php

namespace Database\Seeders;

use App\Models\Automation;
use Illuminate\Database\Seeder;

/**
 * The 22 default automation templates. ALL ship in draft (inactive) mode.
 * Sensitive automations (anything that sends outbound communications) are
 * flagged and cannot run until an administrator explicitly approves them.
 */
class AutomationSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['New lead acknowledgment', 'lead.created', [],
                [['type' => 'send_email', 'params' => ['template' => 'inquiry_acknowledgment']]], true],
            ['Duplicate-case check reminder', 'lead.created', [],
                [['type' => 'create_task', 'params' => ['title' => 'Review possible duplicates flagged on this lead', 'due_in_days' => 2]]], false],
            ['Researcher assignment by county', 'lead.created', [['field' => 'state', 'operator' => 'equals', 'value' => 'MD']],
                [['type' => 'add_note', 'params' => ['body' => 'Assignment rules applied at intake; confirm researcher coverage for this county.']]], false],
            ['Preliminary research checklist', 'case.created', [],
                [['type' => 'create_task', 'params' => ['title' => 'Pull sale record from public source', 'due_in_days' => 5]],
                 ['type' => 'create_task', 'params' => ['title' => 'Identify funds holder', 'due_in_days' => 5]],
                 ['type' => 'create_task', 'params' => ['title' => 'Estimate possible surplus from records', 'due_in_days' => 7]]], false],
            ['Surplus-verification task', 'case.stage_changed', [['field' => 'to_stage', 'operator' => 'equals', 'value' => 'possible_surplus_identified']],
                [['type' => 'create_task', 'params' => ['title' => 'Verify surplus with funds holder', 'due_in_days' => 7, 'priority' => 'high']]], false],
            ['Source recheck every 14 days', 'schedule.tick', [],
                [['type' => 'reverify_source', 'params' => []]], false],
            ['Stale source reminder', 'source.stale', [],
                [['type' => 'create_task', 'params' => ['title' => 'Source record went stale — re-verify before further action', 'due_in_days' => 3, 'priority' => 'high']]], false],
            ['Contact-review request after verification', 'case.stage_changed', [['field' => 'to_stage', 'operator' => 'equals', 'value' => 'surplus_verified']],
                [['type' => 'change_stage', 'params' => ['stage' => 'outreach_compliance_review']],
                 ['type' => 'send_internal_notification', 'params' => ['message' => 'Case verified — outreach compliance review requested.']]], false],
            ['Follow-up after unsuccessful contact', 'case.stage_changed', [['field' => 'to_stage', 'operator' => 'equals', 'value' => 'contact_attempted']],
                [['type' => 'schedule_follow_up', 'params' => ['title' => 'Second contact attempt', 'due_in_days' => 7]]], false],
            ['Missing-document reminder', 'schedule.tick', [['field' => 'missing_documents', 'operator' => 'is_true', 'value' => true]],
                [['type' => 'send_email', 'params' => ['template' => 'document_request']]], true],
            ['Attorney-review escalation', 'case.field_changed', [['field' => 'legal_complexity', 'operator' => 'not_in', 'value' => ['standard']]],
                [['type' => 'require_attorney_review', 'params' => []]], false],
            ['Upcoming deadline alert', 'deadline.approaching', [],
                [['type' => 'send_internal_notification', 'params' => ['message' => 'A case deadline is approaching within 7 days.']]], false],
            ['Overdue-task escalation', 'task.overdue', [],
                [['type' => 'escalate_to_manager', 'params' => ['message' => 'A task on this case is overdue.']]], false],
            ['Claim-filed status notification', 'claim.filed', [],
                [['type' => 'send_portal_notification', 'params' => ['message' => 'Your claim has been submitted to the funds holder.']]], true],
            ['Periodic docket recheck', 'schedule.tick', [['field' => 'stage', 'operator' => 'equals', 'value' => 'awaiting_decision']],
                [['type' => 'create_task', 'params' => ['title' => 'Re-check docket / funds holder status', 'due_in_days' => 1]]], false],
            ['Payment-reconciliation task', 'payment.entered', [],
                [['type' => 'create_task', 'params' => ['title' => 'Reconcile payment against claim and agreement', 'due_in_days' => 3]]], false],
            ['Client completion message', 'case.stage_changed', [['field' => 'to_stage', 'operator' => 'equals', 'value' => 'closed_successfully']],
                [['type' => 'send_email', 'params' => ['template' => 'case_completion']]], true],
            ['Closed-case archive process', 'case.stage_changed', [['field' => 'to_stage', 'operator' => 'contains', 'value' => 'closed']],
                [['type' => 'create_task', 'params' => ['title' => 'Archive file: confirm documents, retention category, and final notes', 'due_in_days' => 7]],
                 ['type' => 'stop_outreach', 'params' => []]], false],
            ['Daily employee work summary', 'schedule.tick', [],
                [['type' => 'send_internal_notification', 'params' => ['message' => 'Daily summary: review your task list and case activity for today.']]], false],
            ['Weekly manager pipeline report', 'schedule.tick', [],
                [['type' => 'send_internal_notification', 'params' => ['message' => 'Weekly pipeline review: open the Reporting Center for current numbers.']]], false],
            ['Monthly accounting workbook', 'schedule.tick', [],
                [['type' => 'create_task', 'params' => ['title' => 'Generate monthly accounting reconciliation workbook from Print & Export Center', 'due_in_days' => 2]]], false],
            ['Monthly compliance audit report', 'schedule.tick', [],
                [['type' => 'create_task', 'params' => ['title' => 'Generate monthly audit report and review consent/opt-out logs', 'due_in_days' => 2]]], false],
        ];

        foreach ($definitions as [$name, $trigger, $conditions, $actions, $sensitive]) {
            Automation::query()->updateOrCreate(['name' => $name], [
                'description' => 'Default template automation. Review conditions and actions, then activate deliberately.',
                'trigger_event' => $trigger,
                'conditions' => $conditions,
                'actions' => $actions,
                'mode' => 'draft', // nothing runs until an administrator activates it
                'is_sensitive' => $sensitive,
            ]);
        }
    }
}
