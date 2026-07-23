<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Services\CaseConversionService;
use App\Services\DuplicateDetectionService;
use App\Services\LeadIntakeService;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $leads = Lead::query()
            ->with('assignee')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('county'), fn ($q) => $q->where('property_county', $request->string('county')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($qq) => $qq->where('lead_number', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('property_address', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return view('portal.leads.index', compact('leads'));
    }

    public function create()
    {
        return view('portal.leads.create');
    }

    public function store(Request $request, LeadIntakeService $intake)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'former_owner_name' => ['nullable', 'string', 'max:160'],
            'relationship_to_owner' => ['nullable', 'string', 'max:120'],
            'property_address' => ['nullable', 'string', 'max:200'],
            'property_county' => ['nullable', 'string', 'max:80'],
            'property_state' => ['nullable', 'string', 'size:2'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:5000'],
            'consent_to_contact' => ['nullable', 'boolean'],
        ]);

        $lead = $intake->create($data, $request->ip(), 'staff_entry');

        return redirect()->route('portal.leads.show', $lead)->with('status', __('Lead :number created.', ['number' => $lead->lead_number]));
    }

    public function show(Lead $lead, DuplicateDetectionService $duplicates)
    {
        $lead->load(['assignee', 'tasks.assignee', 'notes.author', 'consentRecords', 'case']);

        return view('portal.leads.show', [
            'lead' => $lead,
            'possibleDuplicates' => $duplicates->forLead($lead),
            'staff' => User::query()->where('user_type', 'staff')->whereNull('deactivated_at')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:new,screening,duplicate,converted,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $lead->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('status', __('Lead updated.'));
    }

    public function convert(Lead $lead, CaseConversionService $conversion)
    {
        abort_if($lead->status === 'converted', 422, 'Lead already converted.');

        $case = $conversion->fromLead($lead, auth()->id());

        return redirect()->route('portal.cases.show', $case)
            ->with('status', __('Case :number created from lead.', ['number' => $case->case_number]));
    }

    public function markDuplicate(Request $request, Lead $lead)
    {
        $data = $request->validate(['duplicate_of_lead_id' => ['required', 'exists:leads,id']]);
        $lead->update(['status' => 'duplicate', 'duplicate_of_lead_id' => $data['duplicate_of_lead_id']]);

        return back()->with('status', __('Lead marked as duplicate.'));
    }
}
