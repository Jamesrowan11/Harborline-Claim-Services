@extends('layouts.portal')
@section('title', __('Cases'))
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-serif text-2xl font-bold">{{ __('Cases') }}</h1>
    @can('cases.create')<a href="{{ route('portal.cases.create') }}" class="btn-primary !py-2">{{ __('New Case') }}</a>@endcan
</div>
<form class="no-print mt-4 flex flex-wrap gap-3" method="get">
    <input class="form-input max-w-56 !py-2" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Case #, claimant, address…') }}">
    <select class="form-input max-w-64 !py-2" name="stage">
        <option value="">{{ __('All stages') }}</option>
        @foreach ($stages as $stage)<option value="{{ $stage->key }}" @selected(request('stage') === $stage->key)>{{ $stage->name }}</option>@endforeach
    </select>
    <input class="form-input max-w-40 !py-2" name="county" value="{{ request('county') }}" placeholder="{{ __('County') }}">
    <button class="btn-outline !py-2">{{ __('Filter') }}</button>
</form>
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr>
            <th class="table-th">{{ __('Case #') }}</th><th class="table-th">{{ __('Stage') }}</th>
            <th class="table-th">{{ __('County') }}</th><th class="table-th">{{ __('Property') }}</th>
            <th class="table-th text-right">{{ __('Est. Surplus') }}</th><th class="table-th text-right">{{ __('Verified') }}</th>
            <th class="table-th">{{ __('Assigned') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-navy-50">
            @forelse ($cases as $case)
                <tr class="hover:bg-navy-50/50">
                    <td class="table-td font-mono"><a class="underline" href="{{ route('portal.cases.show', $case) }}">{{ $case->case_number }}</a></td>
                    <td class="table-td"><span class="badge bg-navy-100 text-navy-700">{{ $case->stage?->name }}</span></td>
                    <td class="table-td">{{ $case->county }}</td>
                    <td class="table-td">{{ Str::limit($case->property?->address_line1, 35) }}</td>
                    <td class="table-td text-right">{{ $case->estimated_surplus ? '$'.number_format((float) $case->estimated_surplus, 2) : '—' }}</td>
                    <td class="table-td text-right">{{ $case->verified_surplus ? '$'.number_format((float) $case->verified_surplus, 2) : '—' }}</td>
                    <td class="table-td">{{ $case->assignee?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-td py-8 text-center text-navy-400">{{ __('No cases found.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $cases->links() }}</div>
@endsection
