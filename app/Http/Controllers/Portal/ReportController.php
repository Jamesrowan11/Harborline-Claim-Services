<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PipelineStage;
use App\Models\StageTransition;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->subMonths(6)->startOfMonth();
        $to = $request->date('to') ?? now();

        $monthlyRecoveries = Payment::query()
            ->where('payment_type', 'funds_received')
            ->whereBetween('occurred_on', [$from, $to])
            ->get()
            ->groupBy(fn ($p) => $p->occurred_on->format('Y-m'))
            ->map(fn ($group) => $group->sum('amount'))
            ->sortKeys();

        $closedStages = PipelineStage::query()->where('is_closed', true)->pluck('id');
        $funnel = [
            'leads' => Lead::query()->whereBetween('created_at', [$from, $to])->count(),
            'cases' => CaseFile::query()->whereBetween('created_at', [$from, $to])->count(),
            'agreements_signed' => \App\Models\Agreement::query()->where('status', 'signed')->whereBetween('signed_at', [$from, $to])->count(),
            'claims_filed' => \App\Models\Claim::query()->whereBetween('filed_at', [$from, $to])->count(),
            'funds_received' => Payment::query()->where('payment_type', 'funds_received')->whereBetween('occurred_on', [$from, $to])->count(),
        ];

        $avgDurationDays = CaseFile::query()
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$from, $to])
            ->get()
            ->map(fn ($c) => $c->created_at->diffInDays($c->closed_at))
            ->avg();

        $contactAttempts = \App\Models\Communication::query()
            ->where('direction', 'outbound')->whereBetween('created_at', [$from, $to])->count();
        $contactSuccesses = \App\Models\Communication::query()
            ->where('direction', 'inbound')->whereBetween('created_at', [$from, $to])->count();

        $workload = \App\Models\User::query()->where('user_type', 'staff')
            ->withCount(['assignedCases as open_cases' => fn ($q) => $q->whereNotIn('pipeline_stage_id', $closedStages)])
            ->withCount(['tasks as open_tasks' => fn ($q) => $q->where('status', '!=', 'done')])
            ->orderByDesc('open_cases')->get();

        return view('portal.reports.index', compact('monthlyRecoveries', 'funnel', 'avgDurationDays', 'contactAttempts', 'contactSuccesses', 'workload', 'from', 'to'));
    }
}
