<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\CaseFile;
use App\Models\CaseTask;
use App\Models\Claim;
use App\Models\Deadline;
use App\Models\DocumentRequest;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PipelineStage;
use App\Models\SourceRecord;
use App\Models\SurplusRecord;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Central definition of every printable/exportable spreadsheet. Each report
 * declares its columns (label, value resolver, format) and a query builder.
 * The Print & Export Center, CSV/XLSX/PDF exporters, and print views all
 * consume the same definitions, so output stays consistent.
 */
class ReportRegistry
{
    public const CURRENCY = 'currency';
    public const DATE = 'date';

    public function all(): array
    {
        return [
            'master_cases' => ['name' => __('Master Case Spreadsheet'), 'group' => 'Cases'],
            'leads' => ['name' => __('Lead Spreadsheet'), 'group' => 'Leads'],
            'surplus_verification' => ['name' => __('Surplus-Verification Spreadsheet'), 'group' => 'Research'],
            'claimant_contact' => ['name' => __('Claimant-Contact Spreadsheet'), 'group' => 'Outreach'],
            'missing_documents' => ['name' => __('Missing-Documents Spreadsheet'), 'group' => 'Documents'],
            'attorney_review' => ['name' => __('Attorney-Review Spreadsheet'), 'group' => 'Legal'],
            'claim_status' => ['name' => __('Claim-Status Spreadsheet'), 'group' => 'Claims'],
            'payments' => ['name' => __('Payment Spreadsheet'), 'group' => 'Financial'],
            'revenue' => ['name' => __('Revenue Spreadsheet'), 'group' => 'Financial'],
            'employee_workload' => ['name' => __('Employee Workload Spreadsheet'), 'group' => 'Team'],
            'deadlines' => ['name' => __('Deadline Spreadsheet'), 'group' => 'Work'],
            'county_summary' => ['name' => __('County-by-County Spreadsheet'), 'group' => 'Cases'],
            'closed_cases' => ['name' => __('Closed-Case Spreadsheet'), 'group' => 'Cases'],
            'source_verification' => ['name' => __('Source-Verification Report'), 'group' => 'Research'],
            'audit' => ['name' => __('Audit Report'), 'group' => 'Compliance'],
            'accounting_reconciliation' => ['name' => __('Accounting Reconciliation Report'), 'group' => 'Financial'],
        ];
    }

    public function columns(string $report): array
    {
        $case = fn (string $label, callable $value, ?string $format = null) => compact('label', 'value', 'format');

        return match ($report) {
            'master_cases', 'closed_cases' => [
                $case('Case Number', fn ($r) => $r->case_number),
                $case('Stage', fn ($r) => $r->stage?->name),
                $case('County', fn ($r) => $r->county),
                $case('State', fn ($r) => $r->state),
                $case('Primary Claimant', fn ($r) => $r->claimants->firstWhere('pivot.role', 'primary')?->displayName()),
                $case('Property', fn ($r) => $r->property?->address_line1),
                $case('Estimated Surplus', fn ($r) => $r->estimated_surplus, self::CURRENCY),
                $case('Verified Surplus', fn ($r) => $r->verified_surplus, self::CURRENCY),
                $case('Verification', fn ($r) => $r->verification_level),
                $case('Assigned To', fn ($r) => $r->assignee?->name),
                $case('Opened', fn ($r) => $r->created_at, self::DATE),
                $case('Closed', fn ($r) => $r->closed_at, self::DATE),
            ],
            'leads' => [
                $case('Lead Number', fn ($r) => $r->lead_number),
                $case('Status', fn ($r) => $r->status),
                $case('Name', fn ($r) => $r->fullName()),
                $case('Former Owner', fn ($r) => $r->former_owner_name),
                $case('Property Address', fn ($r) => $r->property_address),
                $case('County', fn ($r) => $r->property_county),
                $case('State', fn ($r) => $r->property_state),
                $case('Email', fn ($r) => $r->email),
                $case('Phone', fn ($r) => $r->phone),
                $case('Consent to Contact', fn ($r) => $r->consent_to_contact ? 'Yes' : 'No'),
                $case('Assigned To', fn ($r) => $r->assignee?->name),
                $case('Received', fn ($r) => $r->created_at, self::DATE),
            ],
            'surplus_verification' => [
                $case('Case Number', fn ($r) => $r->case?->case_number),
                $case('Status', fn ($r) => $r->status),
                $case('Estimated', fn ($r) => $r->estimated_amount, self::CURRENCY),
                $case('Verified', fn ($r) => $r->verified_amount, self::CURRENCY),
                $case('Funds Holder', fn ($r) => $r->fundsHolder?->name),
                $case('Verified By', fn ($r) => $r->verifier?->name),
                $case('Verified At', fn ($r) => $r->verified_at, self::DATE),
                $case('Expires', fn ($r) => $r->verification_expires_at, self::DATE),
                $case('Notes', fn ($r) => $r->verification_notes),
            ],
            'claimant_contact' => [
                $case('Case Number', fn ($r) => $r->case_number),
                $case('Claimant', fn ($r) => $r->claimants->firstWhere('pivot.role', 'primary')?->displayName()),
                $case('Outreach Approved', fn ($r) => $r->outreach_approved ? 'Yes' : 'No'),
                $case('Last Activity', fn ($r) => $r->last_activity_at, self::DATE),
                $case('Assigned To', fn ($r) => $r->assignee?->name),
                $case('Stage', fn ($r) => $r->stage?->name),
            ],
            'missing_documents' => [
                $case('Case Number', fn ($r) => $r->case?->case_number),
                $case('Document', fn ($r) => $r->name),
                $case('Claimant', fn ($r) => $r->claimant?->displayName()),
                $case('Status', fn ($r) => $r->status),
                $case('Requested', fn ($r) => $r->created_at, self::DATE),
                $case('Due', fn ($r) => $r->due_at, self::DATE),
            ],
            'attorney_review' => [
                $case('Case Number', fn ($r) => $r->case_number),
                $case('Legal Complexity', fn ($r) => $r->legal_complexity),
                $case('Stage', fn ($r) => $r->stage?->name),
                $case('Attorney', fn ($r) => $r->attorney?->name),
                $case('County', fn ($r) => $r->county),
                $case('Verified Surplus', fn ($r) => $r->verified_surplus, self::CURRENCY),
                $case('Opened', fn ($r) => $r->created_at, self::DATE),
            ],
            'claim_status' => [
                $case('Case Number', fn ($r) => $r->case?->case_number),
                $case('Claim Status', fn ($r) => $r->status),
                $case('Funds Holder', fn ($r) => $r->fundsHolder?->name),
                $case('Amount Claimed', fn ($r) => $r->amount_claimed, self::CURRENCY),
                $case('Amount Approved', fn ($r) => $r->amount_approved, self::CURRENCY),
                $case('Filed', fn ($r) => $r->filed_at, self::DATE),
                $case('Decision', fn ($r) => $r->decision_at, self::DATE),
            ],
            'payments', 'revenue', 'accounting_reconciliation' => [
                $case('Case Number', fn ($r) => $r->case?->case_number),
                $case('Type', fn ($r) => str_replace('_', ' ', $r->payment_type)),
                $case('Amount', fn ($r) => $r->amount, self::CURRENCY),
                $case('Method', fn ($r) => $r->method),
                $case('Reference', fn ($r) => $r->reference),
                $case('Date', fn ($r) => $r->occurred_on, self::DATE),
                $case('Recorded By', fn ($r) => $r->recorder?->name),
                $case('Notes', fn ($r) => $r->notes),
            ],
            'employee_workload' => [
                $case('Employee', fn ($r) => $r->name),
                $case('Title', fn ($r) => $r->title),
                $case('Open Cases', fn ($r) => $r->open_cases),
                $case('Open Tasks', fn ($r) => $r->open_tasks),
                $case('Overdue Tasks', fn ($r) => $r->overdue_tasks),
            ],
            'deadlines' => [
                $case('Case Number', fn ($r) => $r->case?->case_number),
                $case('Deadline', fn ($r) => $r->name),
                $case('Due', fn ($r) => $r->due_at, self::DATE),
                $case('Critical', fn ($r) => $r->is_critical ? 'Yes' : 'No'),
                $case('Source', fn ($r) => $r->source),
                $case('Met', fn ($r) => $r->met_at, self::DATE),
            ],
            'county_summary' => [
                $case('County', fn ($r) => $r->county),
                $case('State', fn ($r) => $r->state),
                $case('Cases', fn ($r) => $r->total_cases),
                $case('Estimated Surplus', fn ($r) => $r->total_estimated, self::CURRENCY),
                $case('Verified Surplus', fn ($r) => $r->total_verified, self::CURRENCY),
            ],
            'source_verification' => [
                $case('Case Number', fn ($r) => $r->case?->case_number),
                $case('Source Type', fn ($r) => $r->source_type),
                $case('Title', fn ($r) => $r->title),
                $case('Reference', fn ($r) => $r->reference),
                $case('Retrieved', fn ($r) => $r->retrieved_at, self::DATE),
                $case('Stale After', fn ($r) => $r->stale_after, self::DATE),
                $case('Status', fn ($r) => $r->status),
            ],
            'audit' => [
                $case('When', fn ($r) => $r->created_at, self::DATE),
                $case('User', fn ($r) => $r->user?->name ?? 'System'),
                $case('Event', fn ($r) => $r->event),
                $case('Record', fn ($r) => class_basename((string) $r->auditable_type).' #'.$r->auditable_id),
                $case('IP', fn ($r) => $r->ip_address),
            ],
            default => throw new \InvalidArgumentException("Unknown report: $report"),
        };
    }

    public function query(string $report, array $filters = []): Collection
    {
        $from = isset($filters['from']) ? \Illuminate\Support\Carbon::parse($filters['from']) : null;
        $to = isset($filters['to']) ? \Illuminate\Support\Carbon::parse($filters['to'])->endOfDay() : null;
        $between = fn ($q, string $column) => $q
            ->when($from, fn ($qq) => $qq->where($column, '>=', $from))
            ->when($to, fn ($qq) => $qq->where($column, '<=', $to));
        $closedStages = PipelineStage::query()->where('is_closed', true)->pluck('id');

        return match ($report) {
            'master_cases' => $between(CaseFile::query()->with(['stage', 'property', 'assignee', 'claimants'])
                ->when($filters['county'] ?? null, fn ($q, $county) => $q->where('county', $county)), 'created_at')
                ->orderBy('case_number')->get(),
            'closed_cases' => $between(CaseFile::query()->with(['stage', 'property', 'assignee', 'claimants'])
                ->whereIn('pipeline_stage_id', $closedStages), 'closed_at')->orderByDesc('closed_at')->get(),
            'leads' => $between(Lead::query()->with('assignee'), 'created_at')->orderByDesc('created_at')->get(),
            'surplus_verification' => $between(SurplusRecord::query()->with(['case', 'fundsHolder', 'verifier']), 'created_at')->get(),
            'claimant_contact' => CaseFile::query()->with(['stage', 'assignee', 'claimants'])
                ->whereNotIn('pipeline_stage_id', $closedStages)->orderBy('case_number')->get(),
            'missing_documents' => DocumentRequest::query()->with(['case', 'claimant'])
                ->whereIn('status', ['requested'])->orderBy('due_at')->get(),
            'attorney_review' => CaseFile::query()->with(['stage', 'attorney'])
                ->where(fn ($q) => $q->where('legal_complexity', '!=', 'standard')
                    ->orWhereHas('stage', fn ($s) => $s->where('requires_attorney_review', true)))
                ->orderBy('case_number')->get(),
            'claim_status' => $between(Claim::query()->with(['case', 'fundsHolder']), 'created_at')->get(),
            'payments' => $between(Payment::query()->with(['case', 'recorder']), 'occurred_on')->orderBy('occurred_on')->get(),
            'revenue' => $between(Payment::query()->with(['case', 'recorder'])->where('payment_type', 'company_fee'), 'occurred_on')->orderBy('occurred_on')->get(),
            'accounting_reconciliation' => $between(Payment::query()->with(['case', 'recorder']), 'occurred_on')->orderBy('case_id')->orderBy('occurred_on')->get(),
            'employee_workload' => User::query()->where('user_type', 'staff')->whereNull('deactivated_at')
                ->withCount(['assignedCases as open_cases' => fn ($q) => $q->whereNotIn('pipeline_stage_id', $closedStages)])
                ->withCount(['tasks as open_tasks' => fn ($q) => $q->where('status', '!=', 'done')])
                ->withCount(['tasks as overdue_tasks' => fn ($q) => $q->where('status', '!=', 'done')->where('due_at', '<', now())])
                ->orderBy('name')->get(),
            'deadlines' => $between(Deadline::query()->with('case')->whereNull('met_at'), 'due_at')->orderBy('due_at')->get(),
            'county_summary' => CaseFile::query()
                ->selectRaw('county, state, count(*) as total_cases, sum(estimated_surplus) as total_estimated, sum(verified_surplus) as total_verified')
                ->whereNotNull('county')->groupBy('county', 'state')->orderBy('county')->get(),
            'source_verification' => $between(SourceRecord::query()->with('case'), 'retrieved_at')->orderBy('stale_after')->get(),
            'audit' => $between(AuditEvent::query()->with('user'), 'created_at')->orderByDesc('id')->limit(5000)->get(),
            default => throw new \InvalidArgumentException("Unknown report: $report"),
        };
    }

    /** Rows as plain arrays for CSV/XLSX/PDF. */
    public function rows(string $report, array $filters = []): array
    {
        $columns = $this->columns($report);
        $records = $this->query($report, $filters);

        return $records->map(function ($record) use ($columns) {
            return array_map(function ($column) use ($record) {
                $value = ($column['value'])($record);
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d');
                }

                return $value;
            }, $columns);
        })->all();
    }
}
