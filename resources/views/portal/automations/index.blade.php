@extends('layouts.portal')
@section('title', __('Automation Center'))
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-serif text-2xl font-bold">{{ __('Automation Center') }}</h1>
    <div class="flex gap-2">
        @can('automations.approve')
        <form method="post" action="{{ route('portal.automations.stop') }}">
            @csrf<input type="hidden" name="stop" value="{{ $globalStop ? 0 : 1 }}">
            <button class="btn-outline !py-2 {{ $globalStop ? '' : '!border-burgundy-600 !text-burgundy-600' }}">
                {{ $globalStop ? __('Lift Emergency Stop') : __('EMERGENCY STOP') }}
            </button>
        </form>
        @endcan
        @can('automations.manage')<a href="{{ route('portal.automations.create') }}" class="btn-primary !py-2">{{ __('New Automation') }}</a>@endcan
    </div>
</div>
@if ($globalStop)
    <p class="mt-4 rounded-md border border-burgundy-600 bg-burgundy-500/10 px-4 py-3 font-semibold text-burgundy-700">{{ __('Global emergency stop is ACTIVE. No automations are running.') }}</p>
@endif
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr><th class="table-th">{{ __('Name') }}</th><th class="table-th">{{ __('Trigger') }}</th><th class="table-th">{{ __('Mode') }}</th><th class="table-th">{{ __('Sensitive') }}</th><th class="table-th">{{ __('Runs') }}</th><th class="table-th">{{ __('Failures') }}</th></tr></thead>
        <tbody class="divide-y divide-navy-50">
            @foreach ($automations as $automation)
                <tr>
                    <td class="table-td"><a class="font-medium underline" href="{{ route('portal.automations.edit', $automation) }}">{{ $automation->name }}</a>{{ $automation->paused ? ' ⏸' : '' }}</td>
                    <td class="table-td font-mono text-xs">{{ $automation->trigger_event }}</td>
                    <td class="table-td"><span class="badge {{ $automation->mode === 'active' ? 'bg-gold-100 text-gold-800' : 'bg-navy-100' }}">{{ $automation->mode }}</span></td>
                    <td class="table-td">{{ $automation->is_sensitive ? ($automation->approved_at ? __('approved') : __('needs approval')) : '—' }}</td>
                    <td class="table-td">{{ $automation->runs_count }}</td>
                    <td class="table-td {{ $automation->failed_runs_count ? 'font-semibold text-burgundy-600' : '' }}">{{ $automation->failed_runs_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
