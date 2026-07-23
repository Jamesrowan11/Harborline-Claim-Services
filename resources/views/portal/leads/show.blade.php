@extends('layouts.portal')
@section('title', $lead->lead_number)
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-serif text-2xl font-bold">{{ $lead->lead_number }} <span class="badge ml-2 bg-navy-100 text-navy-700">{{ $lead->status }}</span></h1>
    <div class="flex gap-2">
        @if ($lead->status !== 'converted')
            @can('cases.create')
                <form method="post" action="{{ route('portal.leads.convert', $lead) }}">@csrf<button class="btn-primary !py-2">{{ __('Convert to Case') }}</button></form>
            @endcan
        @elseif ($lead->case)
            <a href="{{ route('portal.cases.show', $lead->case) }}" class="btn-outline !py-2">{{ __('Open Case :n', ['n' => $lead->case->case_number]) }}</a>
        @endif
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        <h2 class="font-serif text-lg font-bold">{{ __('Inquiry Details') }}</h2>
        <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            @foreach ([
                __('Name') => $lead->fullName(), __('Former owner') => $lead->former_owner_name,
                __('Relationship') => $lead->relationship_to_owner,
                __('Owner living?') => $lead->former_owner_living === null ? '—' : ($lead->former_owner_living ? __('Yes') : __('No')),
                __('Property') => $lead->property_address, __('County') => $lead->property_county.', '.$lead->property_state,
                __('Approx. sale date') => $lead->approximate_sale_date, __('Court case #') => $lead->court_case_number,
                __('Parcel #') => $lead->parcel_number, __('Tax account #') => $lead->tax_account_number,
                __('Email') => $lead->email, __('Phone') => $lead->phone,
                __('Preferred contact') => $lead->preferred_contact_method, __('Source') => $lead->source,
            ] as $label => $value)
                <div><dt class="font-semibold text-navy-500">{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
            @endforeach
        </dl>
        @if ($lead->description)<p class="mt-4 rounded bg-navy-50 p-3 text-sm">{{ $lead->description }}</p>@endif
        <h3 class="mt-5 font-serif font-bold">{{ __('Consents') }}</h3>
        <ul class="mt-2 flex flex-wrap gap-2 text-xs">
            @foreach ($lead->consentRecords as $consent)
                <li class="badge {{ $consent->granted ? 'bg-gold-100 text-gold-800' : 'bg-navy-100 text-navy-500' }}">
                    {{ $consent->consent_type }}: {{ $consent->granted ? __('granted') : __('declined') }}
                </li>
            @endforeach
        </ul>
    </div>
    <div class="space-y-6">
        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Assignment') }}</h2>
            <form method="post" action="{{ route('portal.leads.update', $lead) }}" class="mt-3 space-y-3">
                @csrf @method('PUT')
                <select class="form-input" name="assigned_to">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected($lead->assigned_to === $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
                <select class="form-input" name="status">
                    @foreach (['new', 'screening', 'duplicate', 'converted', 'closed'] as $status)
                        <option value="{{ $status }}" @selected($lead->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="btn-outline !py-2 w-full">{{ __('Save') }}</button>
            </form>
        </div>
        @if ($possibleDuplicates->isNotEmpty())
            <div class="card border-l-4 border-l-burgundy-600">
                <h2 class="font-serif text-lg font-bold">{{ __('Possible Duplicates') }}</h2>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($possibleDuplicates as $dup)
                        <li>
                            <span class="font-mono">{{ $dup['record']->lead_number ?? $dup['record']->case_number }}</span>
                            <span class="text-navy-500">— {{ $dup['reason'] }}</span>
                            @if ($dup['type'] === 'lead')
                                <form method="post" action="{{ route('portal.leads.duplicate', $lead) }}" class="mt-1 inline">
                                    @csrf<input type="hidden" name="duplicate_of_lead_id" value="{{ $dup['record']->id }}">
                                    <button class="text-xs underline">{{ __('Mark this lead as duplicate') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Tasks') }}</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($lead->tasks as $task)
                    <li class="{{ $task->status === 'done' ? 'line-through text-navy-300' : '' }}">{{ $task->title }}</li>
                @endforeach
            </ul>
        </div>
        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Notes') }}</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($lead->notes as $note)
                    <li class="rounded bg-navy-50 p-2">{{ $note->body }}<span class="block text-xs text-navy-400">{{ $note->author?->name }} — {{ $note->created_at->format('M j, Y') }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
