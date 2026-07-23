<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AutomationRun;
use App\Models\CaseFile;
use App\Models\CaseTask;
use App\Models\Deadline;
use App\Models\DocumentRequest;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PipelineStage;
use App\Models\SurplusRecord;

class DashboardController extends Controller
{
    public function index()
    {
        $closedStageIds = PipelineStage::query()->where('is_closed', true)->pluck('id');

        $widgets = [
            'new_leads' => Lead::query()->where('status', 'new')->count(),
            'active_cases' => CaseFile::query()->whereNotIn('pipeline_stage_id', $closedStageIds)->count(),
            'verified_surplus' => CaseFile::query()->whereNotIn('pipeline_stage_id', $closedStageIds)->sum('verified_surplus'),
            'estimated_surplus' => CaseFile::query()->whereNotIn('pipeline_stage_id', $closedStageIds)->sum('estimated_surplus'),
            'claims_filed' => \App\Models\Claim::query()->whereNotNull('filed_at')->count(),
            'funds_received' => Payment::query()->where('payment_type', 'funds_received')->sum('amount'),
            'client_distributions' => Payment::query()->where('payment_type', 'client_distribution')->sum('amount'),
            'company_revenue' => Payment::query()->where('payment_type', 'company_fee')->sum('amount'),
            'overdue_tasks' => CaseTask::query()->where('status', '!=', 'done')->where('due_at', '<', now())->count(),
            'upcoming_deadlines' => Deadline::query()->whereNull('met_at')->whereBetween('due_at', [now(), now()->addDays(14)])->count(),
            'stale_verifications' => SurplusRecord::query()->where('verification_expires_at', '<', now())->count(),
            'missing_documents' => DocumentRequest::query()->where('status', 'requested')->count(),
            'automation_failures' => AutomationRun::query()->where('status', 'failed')->where('created_at', '>=', now()->subWeek())->count(),
        ];

        $casesByStage = PipelineStage::query()->withCount('cases')->orderBy('sort_order')->get();
        $casesByCounty = CaseFile::query()->selectRaw('county, count(*) as total')
            ->whereNotNull('county')->groupBy('county')->orderByDesc('total')->limit(10)->get();
        $myTasks = CaseTask::query()->with('case')->where('assigned_to', auth()->id())
            ->where('status', '!=', 'done')->orderBy('due_at')->limit(10)->get();

        return view('portal.dashboard', compact('widgets', 'casesByStage', 'casesByCounty', 'myTasks'));
    }
}
