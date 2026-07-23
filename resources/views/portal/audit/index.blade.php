@extends('layouts.portal')
@section('title', __('Audit Log'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Audit Log') }}</h1>
<form class="no-print mt-4 flex flex-wrap gap-3" method="get">
    <input class="form-input max-w-48 !py-2" name="event" value="{{ request('event') }}" placeholder="{{ __('Event (e.g. login)') }}">
    <input class="form-input !py-2" type="date" name="from" value="{{ request('from') }}">
    <input class="form-input !py-2" type="date" name="to" value="{{ request('to') }}">
    <button class="btn-outline !py-2">{{ __('Filter') }}</button>
</form>
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr><th class="table-th">{{ __('When') }}</th><th class="table-th">{{ __('User') }}</th><th class="table-th">{{ __('Event') }}</th><th class="table-th">{{ __('Record') }}</th><th class="table-th">{{ __('IP') }}</th><th class="table-th">{{ __('Changes') }}</th></tr></thead>
        <tbody class="divide-y divide-navy-50">
            @foreach ($events as $event)
                <tr>
                    <td class="table-td text-xs">{{ $event->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="table-td">{{ $event->user?->name ?? __('System') }}</td>
                    <td class="table-td"><span class="badge bg-navy-100">{{ $event->event }}</span></td>
                    <td class="table-td text-xs">{{ $event->auditable_type ? class_basename($event->auditable_type).' #'.$event->auditable_id : '—' }}</td>
                    <td class="table-td text-xs">{{ $event->ip_address }}</td>
                    <td class="table-td text-xs">
                        @if ($event->new_values)
                            <details><summary class="cursor-pointer underline">{{ __('view') }}</summary>
                                <pre class="mt-1 max-w-md overflow-x-auto rounded bg-navy-50 p-2 text-xs">{{ json_encode($event->new_values, JSON_PRETTY_PRINT) }}</pre></details>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $events->links() }}</div>
@endsection
