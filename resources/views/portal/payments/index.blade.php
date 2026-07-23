@extends('layouts.portal')
@section('title', __('Payments'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Payments') }}</h1>
<div class="mt-4 grid gap-4 sm:grid-cols-4">
    @foreach (['funds_received' => __('Funds Received'), 'client_distribution' => __('Client Distributions'), 'company_fee' => __('Company Fees'), 'refund' => __('Refunds')] as $type => $label)
        <div class="card"><p class="text-xs font-semibold uppercase text-navy-400">{{ $label }}</p>
            <p class="mt-1 font-serif text-xl font-bold">${{ number_format((float) ($totals[$type] ?? 0), 2) }}</p></div>
    @endforeach
</div>
<form class="no-print mt-4 flex flex-wrap gap-3" method="get">
    <select class="form-input max-w-52 !py-2" name="type">
        <option value="">{{ __('All types') }}</option>
        @foreach (['funds_received', 'client_distribution', 'company_fee', 'refund'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ str_replace('_', ' ', $type) }}</option>@endforeach
    </select>
    <input class="form-input !py-2" type="date" name="from" value="{{ request('from') }}">
    <input class="form-input !py-2" type="date" name="to" value="{{ request('to') }}">
    <button class="btn-outline !py-2">{{ __('Filter') }}</button>
</form>
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr><th class="table-th">{{ __('Date') }}</th><th class="table-th">{{ __('Case') }}</th><th class="table-th">{{ __('Type') }}</th><th class="table-th text-right">{{ __('Amount') }}</th><th class="table-th">{{ __('Method') }}</th><th class="table-th">{{ __('Reference') }}</th><th class="table-th">{{ __('Recorded by') }}</th></tr></thead>
        <tbody class="divide-y divide-navy-50">
            @forelse ($payments as $payment)
                <tr>
                    <td class="table-td">{{ $payment->occurred_on->format('M j, Y') }}</td>
                    <td class="table-td font-mono text-xs"><a class="underline" href="{{ route('portal.cases.show', $payment->case) }}">{{ $payment->case?->case_number }}</a></td>
                    <td class="table-td">{{ str_replace('_', ' ', $payment->payment_type) }}</td>
                    <td class="table-td text-right font-semibold">${{ number_format((float) $payment->amount, 2) }}</td>
                    <td class="table-td">{{ $payment->method }}</td>
                    <td class="table-td">{{ $payment->reference }}</td>
                    <td class="table-td">{{ $payment->recorder?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-td py-8 text-center text-navy-400">{{ __('No payments recorded.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
