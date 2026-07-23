@extends('layouts.portal')
@section('title', __('Leads'))
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-serif text-2xl font-bold">{{ __('Leads') }}</h1>
    @can('leads.create')<a href="{{ route('portal.leads.create') }}" class="btn-primary !py-2">{{ __('New Lead') }}</a>@endcan
</div>
<form class="no-print mt-4 flex flex-wrap gap-3" method="get">
    <input class="form-input max-w-56 !py-2" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search leads…') }}">
    <select class="form-input max-w-44 !py-2" name="status">
        <option value="">{{ __('All statuses') }}</option>
        @foreach (['new', 'screening', 'duplicate', 'converted', 'closed'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <select class="form-input max-w-32 !py-2" name="per_page">
        @foreach ([25, 50, 100] as $n)<option value="{{ $n }}" @selected(request('per_page', 25) == $n)>{{ $n }}/page</option>@endforeach
    </select>
    <button class="btn-outline !py-2">{{ __('Filter') }}</button>
</form>
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr>
            <th class="table-th">{{ __('Lead #') }}</th><th class="table-th">{{ __('Name') }}</th>
            <th class="table-th">{{ __('Property') }}</th><th class="table-th">{{ __('County') }}</th>
            <th class="table-th">{{ __('Status') }}</th><th class="table-th">{{ __('Assigned') }}</th><th class="table-th">{{ __('Received') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-navy-50">
            @forelse ($leads as $lead)
                <tr class="hover:bg-navy-50/50">
                    <td class="table-td font-mono"><a class="underline" href="{{ route('portal.leads.show', $lead) }}">{{ $lead->lead_number }}</a></td>
                    <td class="table-td">{{ $lead->fullName() }}</td>
                    <td class="table-td">{{ Str::limit($lead->property_address, 40) }}</td>
                    <td class="table-td">{{ $lead->property_county }}</td>
                    <td class="table-td"><span class="badge bg-navy-100 text-navy-700">{{ $lead->status }}</span></td>
                    <td class="table-td">{{ $lead->assignee?->name }}</td>
                    <td class="table-td">{{ $lead->created_at->format('M j, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-td py-8 text-center text-navy-400">{{ __('No leads found.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $leads->links() }}</div>
@endsection
