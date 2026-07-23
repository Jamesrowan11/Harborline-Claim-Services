@extends('layouts.portal')
@section('title', __('Dashboard'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Dashboard') }}</h1>

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
        ['new_leads', __('New Leads'), null], ['active_cases', __('Active Cases'), null],
        ['estimated_surplus', __('Estimated Surplus'), 'money'], ['verified_surplus', __('Verified Surplus'), 'money'],
        ['claims_filed', __('Claims Filed'), null], ['funds_received', __('Funds Received'), 'money'],
        ['client_distributions', __('Client Distributions'), 'money'], ['company_revenue', __('Company Revenue'), 'money'],
        ['overdue_tasks', __('Overdue Tasks'), null], ['upcoming_deadlines', __('Deadlines (14 days)'), null],
        ['stale_verifications', __('Stale Verifications'), null], ['missing_documents', __('Missing Documents'), null],
    ] as [$key, $label, $fmt])
        <div class="card">
            <p class="text-xs font-semibold uppercase tracking-wide text-navy-400">{{ $label }}</p>
            <p class="mt-1 font-serif text-2xl font-bold text-navy-900">
                {{ $fmt === 'money' ? '$'.number_format((float) $widgets[$key], 2) : number_format((int) $widgets[$key]) }}
            </p>
        </div>
    @endforeach
</div>

@if ($widgets['automation_failures'] > 0)
    <p class="mt-4 rounded-md border border-burgundy-500 bg-burgundy-500/10 px-4 py-3 text-sm">
        {{ __(':n automation failures in the last 7 days.', ['n' => $widgets['automation_failures']]) }}
        <a href="{{ route('portal.automations.index') }}" class="underline">{{ __('Review') }}</a>
    </p>
@endif

<div class="mt-8 grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-1">
        <h2 class="font-serif text-lg font-bold">{{ __('Cases by Stage') }}</h2>
        <ul class="mt-3 max-h-96 space-y-1 overflow-y-auto text-sm">
            @foreach ($casesByStage->where('cases_count', '>', 0) as $stage)
                <li class="flex justify-between"><span>{{ $stage->name }}</span><span class="font-semibold">{{ $stage->cases_count }}</span></li>
            @endforeach
        </ul>
    </div>
    <div class="card">
        <h2 class="font-serif text-lg font-bold">{{ __('Cases by County') }}</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($casesByCounty as $row)
                <li class="flex justify-between"><span>{{ $row->county }}</span><span class="font-semibold">{{ $row->total }}</span></li>
            @endforeach
        </ul>
    </div>
    <div class="card">
        <h2 class="font-serif text-lg font-bold">{{ __('My Open Tasks') }}</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($myTasks as $task)
                <li>
                    <span class="{{ $task->isOverdue() ? 'text-burgundy-600 font-semibold' : '' }}">{{ $task->title }}</span>
                    @if ($task->case)<a href="{{ route('portal.cases.show', $task->case) }}" class="text-navy-400 underline">{{ $task->case->case_number }}</a>@endif
                    @if ($task->due_at)<span class="text-navy-400"> — {{ $task->due_at->format('M j') }}</span>@endif
                </li>
            @empty
                <li class="text-navy-400">{{ __('Nothing assigned. Well done.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
