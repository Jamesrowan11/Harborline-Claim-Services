<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\User;
use App\Services\CaseConversionService;
use App\Services\CaseNumberService;
use App\Services\StageChangeService;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        $cases = CaseFile::query()
            ->with(['stage', 'assignee', 'property'])
            ->when($request->filled('stage'), fn ($q) => $q->whereHas('stage', fn ($qq) => $qq->where('key', $request->string('stage'))))
            ->when($request->filled('county'), fn ($q) => $q->where('county', $request->string('county')))
            ->when($request->filled('assignee'), fn ($q) => $q->where('assigned_to', $request->integer('assignee')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($qq) => $qq->where('case_number', 'like', $term)
                    ->orWhere('county', 'like', $term)
                    ->orWhereHas('property', fn ($p) => $p->where('address_line1', 'like', $term)->orWhere('parcel_number', 'like', $term))
                    ->orWhereHas('claimants', fn ($c) => $c->where('last_name', 'like', $term)->orWhere('organization_name', 'like', $term)));
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $stages = PipelineStage::query()->where('active', true)->orderBy('sort_order')->get();

        return view('portal.cases.index', compact('cases', 'stages'));
    }

    public function create()
    {
        return view('portal.cases.create', [
            'stages' => PipelineStage::query()->where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, CaseNumberService $caseNumbers, CaseConversionService $conversion)
    {
        $data = $request->validate([
            'county' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'size:2'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:10'],
            'parcel_number' => ['nullable', 'string', 'max:60'],
            'estimated_surplus' => ['nullable', 'numeric', 'min:0'],
        ]);

        $countyCode = $conversion->countyCode($data['county'], $data['state']);

        $property = null;
        if (filled($data['address_line1'] ?? null)) {
            $property = Property::query()->create([
                'address_line1' => $data['address_line1'],
                'city' => $data['city'] ?? null,
                'county' => $data['county'],
                'county_code' => $countyCode,
                'state' => strtoupper($data['state']),
                'zip' => $data['zip'] ?? null,
                'parcel_number' => $data['parcel_number'] ?? null,
            ]);
        }

        $case = CaseFile::query()->create([
            'case_number' => $caseNumbers->next(strtoupper($data['state']), $countyCode),
            'pipeline_stage_id' => PipelineStage::query()->where('key', 'new_lead')->value('id'),
            'property_id' => $property?->id,
            'county' => $data['county'],
            'county_code' => $countyCode,
            'state' => strtoupper($data['state']),
            'estimated_surplus' => $data['estimated_surplus'] ?? null,
            'assigned_to' => auth()->id(),
            'case_manager_id' => auth()->id(),
            'last_activity_at' => now(),
        ]);

        event(new \App\Events\CaseCreated($case));

        return redirect()->route('portal.cases.show', $case)->with('status', __('Case :number created.', ['number' => $case->case_number]));
    }

    public function show(CaseFile $case)
    {
        $this->authorize('view', $case);

        $case->load([
            'stage', 'lead', 'property', 'assignee', 'caseManager', 'attorney', 'fundsHolder',
            'claimants.emailAddresses', 'claimants.phoneNumbers', 'surplusRecords.fundsHolder',
            'sourceRecords', 'tasks.assignee', 'deadlines', 'documents', 'documentRequests',
            'communications' => fn ($q) => $q->latest()->limit(20),
            'agreements', 'claims', 'payments', 'notes.author', 'stageTransitions.toStage',
            'stageTransitions.user', 'portalMessages.sender',
        ]);

        return view('portal.cases.show', [
            'case' => $case,
            'stages' => PipelineStage::query()->where('active', true)->orderBy('sort_order')->get(),
            'staff' => User::query()->where('user_type', 'staff')->whereNull('deactivated_at')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CaseFile $case)
    {
        $this->authorize('update', $case);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
            'case_manager_id' => ['nullable', 'exists:users,id'],
            'estimated_surplus' => ['nullable', 'numeric', 'min:0'],
            'verified_surplus' => ['nullable', 'numeric', 'min:0'],
            'verification_level' => ['nullable', 'in:none,preliminary,source_confirmed,holder_confirmed'],
            'risk_level' => ['nullable', 'in:low,normal,elevated,high'],
            'legal_complexity' => ['nullable', 'in:standard,probate,heirship,trust,business,bankruptcy,disputed'],
            'compliance_hold' => ['nullable', 'boolean'],
            'automation_paused' => ['nullable', 'boolean'],
            'outreach_approved' => ['nullable', 'boolean'],
        ]);

        // Outreach approval is restricted to compliance reviewers.
        if (array_key_exists('outreach_approved', $data) && ! $request->user()->can('compliance.review')) {
            unset($data['outreach_approved']);
        }

        $case->update(array_filter($data, fn ($v) => $v !== null));
        $case->touchActivity();

        event(new \App\Events\CaseFieldChanged($case->fresh(), null, ['changed' => array_keys($data)]));

        return back()->with('status', __('Case updated.'));
    }

    public function changeStage(Request $request, CaseFile $case, StageChangeService $stageChange)
    {
        $this->authorize('changeStage', $case);

        $data = $request->validate([
            'stage_id' => ['required', 'exists:pipeline_stages,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $stageChange->change($case, PipelineStage::query()->findOrFail($data['stage_id']), $request->user(), $data['reason'] ?? null);

        return back()->with('status', __('Stage updated.'));
    }

    public function addNote(Request $request, CaseFile $case)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $case->notes()->create(['body' => $data['body'], 'user_id' => auth()->id(), 'is_staff_only' => true]);
        $case->touchActivity();

        return back()->with('status', __('Note added.'));
    }

    public function pipeline()
    {
        $stages = PipelineStage::query()->where('active', true)->where('is_closed', false)
            ->orderBy('sort_order')
            ->with(['cases' => fn ($q) => $q->with('assignee')->orderByDesc('last_activity_at')->limit(15)])
            ->withCount('cases')
            ->get();

        return view('portal.pipeline', compact('stages'));
    }
}
