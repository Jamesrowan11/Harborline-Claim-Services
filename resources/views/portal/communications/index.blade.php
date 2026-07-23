@extends('layouts.portal')
@section('title', __('Communication Center'))
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-serif text-2xl font-bold">{{ __('Communication Center') }}</h1>
    @can('communications.send')<a href="{{ route('portal.communications.compose') }}" class="btn-primary !py-2">{{ __('Compose') }}</a>@endcan
</div>
<form class="no-print mt-4 flex gap-3" method="get">
    <select class="form-input max-w-40 !py-2" name="channel">
        <option value="">{{ __('All channels') }}</option>
        @foreach (['email', 'sms', 'letter', 'call', 'portal_message'] as $channel)<option value="{{ $channel }}" @selected(request('channel') === $channel)>{{ $channel }}</option>@endforeach
    </select>
    <select class="form-input max-w-40 !py-2" name="status">
        <option value="">{{ __('All statuses') }}</option>
        @foreach (['draft', 'queued', 'sent', 'delivered', 'bounced', 'returned_mail', 'failed', 'received'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach
    </select>
    <button class="btn-outline !py-2">{{ __('Filter') }}</button>
</form>
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr><th class="table-th">{{ __('When') }}</th><th class="table-th">{{ __('Case') }}</th><th class="table-th">{{ __('Channel') }}</th><th class="table-th">{{ __('Dir') }}</th><th class="table-th">{{ __('Subject') }}</th><th class="table-th">{{ __('Status') }}</th><th class="table-th"></th></tr></thead>
        <tbody class="divide-y divide-navy-50">
            @forelse ($communications as $communication)
                <tr>
                    <td class="table-td text-xs">{{ $communication->created_at->format('M j, Y H:i') }}</td>
                    <td class="table-td font-mono text-xs">@if ($communication->case)<a class="underline" href="{{ route('portal.cases.show', $communication->case) }}">{{ $communication->case->case_number }}</a>@endif</td>
                    <td class="table-td">{{ $communication->channel }}</td>
                    <td class="table-td">{{ $communication->direction }}</td>
                    <td class="table-td">{{ Str::limit($communication->subject, 50) }}</td>
                    <td class="table-td"><span class="badge bg-navy-100">{{ $communication->status }}</span></td>
                    <td class="table-td space-x-2">
                        @if ($communication->status === 'draft')
                            @if (!$communication->approved_at)
                                @can('communications.approve')<form class="inline" method="post" action="{{ route('portal.communications.approve', $communication) }}">@csrf<button class="underline">{{ __('Approve') }}</button></form>@endcan
                            @else
                                @can('communications.send')<form class="inline" method="post" action="{{ route('portal.communications.send', $communication) }}">@csrf<button class="underline">{{ __('Send') }}</button></form>@endcan
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-td py-8 text-center text-navy-400">{{ __('No communications.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@error('send')<p class="form-error mt-2">{{ $message }}</p>@enderror
<div class="mt-4">{{ $communications->links() }}</div>
@endsection
