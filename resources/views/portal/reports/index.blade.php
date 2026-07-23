@extends('layouts.portal')
@section('title', __('Reporting Center'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Reporting Center') }}</h1>
<form class="no-print mt-4 flex gap-3" method="get">
    <input class="form-input !py-2" type="date" name="from" value="{{ $from->format('Y-m-d') }}">
    <input class="form-input !py-2" type="date" name="to" value="{{ $to->format('Y-m-d') }}">
    <button class="btn-outline !py-2">{{ __('Apply') }}</button>
    <a class="btn-outline !py-2" href="{{ route('portal.print.index') }}">{{ __('Print & Export Center') }} →</a>
</form>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="card">
        <h2 class="font-serif text-lg font-bold">{{ __('Conversion Funnel') }}</h2>
        <dl class="mt-3 space-y-2 text-sm">
            @foreach ([__('Leads received') => $funnel['leads'], __('Cases opened') => $funnel['cases'], __('Agreements signed') => $funnel['agreements_signed'], __('Claims filed') => $funnel['claims_filed'], __('Recoveries received') => $funnel['funds_received']] as $label => $count)
                <div class="flex justify-between"><dt>{{ $label }}</dt><dd class="font-semibold">{{ number_format($count) }}</dd></div>
            @endforeach
        </dl>
    </div>
    <div class="card">
        <h2 class="font-serif text-lg font-bold">{{ __('Monthly Recovery Totals') }}</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($monthlyRecoveries as $month => $total)
                <li class="flex justify-between"><span>{{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }}</span><span class="font-semibold">${{ number_format((float) $total, 2) }}</span></li>
            @empty<li class="text-navy-400">{{ __('No recoveries in this period.') }}</li>@endforelse
        </ul>
    </div>
    <div class="card">
        <h2 class="font-serif text-lg font-bold">{{ __('Operational Metrics') }}</h2>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between"><dt>{{ __('Average case duration (closed in period)') }}</dt><dd class="font-semibold">{{ $avgDurationDays ? round($avgDurationDays).' '.__('days') : '—' }}</dd></div>
            <div class="flex justify-between"><dt>{{ __('Outbound contacts') }}</dt><dd class="font-semibold">{{ number_format($contactAttempts) }}</dd></div>
            <div class="flex justify-between"><dt>{{ __('Inbound responses') }}</dt><dd class="font-semibold">{{ number_format($contactSuccesses) }}</dd></div>
            <div class="flex justify-between"><dt>{{ __('Response rate') }}</dt><dd class="font-semibold">{{ $contactAttempts ? round($contactSuccesses / $contactAttempts * 100, 1).'%' : '—' }}</dd></div>
        </dl>
    </div>
    <div class="card">
        <h2 class="font-serif text-lg font-bold">{{ __('Employee Workload') }}</h2>
        <table class="mt-3 min-w-full text-sm">
            <thead><tr><th class="table-th">{{ __('Employee') }}</th><th class="table-th text-right">{{ __('Open cases') }}</th><th class="table-th text-right">{{ __('Open tasks') }}</th></tr></thead>
            <tbody>
                @foreach ($workload as $member)
                    <tr><td class="table-td">{{ $member->name }}</td><td class="table-td text-right">{{ $member->open_cases }}</td><td class="table-td text-right">{{ $member->open_tasks }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
