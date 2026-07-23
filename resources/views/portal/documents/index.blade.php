@extends('layouts.portal')
@section('title', __('Document Center'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Document Center') }}</h1>
<form class="no-print mt-4 flex gap-3" method="get">
    <select class="form-input max-w-44 !py-2" name="status">
        <option value="">{{ __('All statuses') }}</option>
        @foreach (['pending', 'approved', 'rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach
    </select>
    <button class="btn-outline !py-2">{{ __('Filter') }}</button>
</form>
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr><th class="table-th">{{ __('Title') }}</th><th class="table-th">{{ __('Case') }}</th><th class="table-th">{{ __('Category') }}</th><th class="table-th">{{ __('Status') }}</th><th class="table-th">{{ __('Scan') }}</th><th class="table-th">{{ __('Uploaded') }}</th><th class="table-th"></th></tr></thead>
        <tbody class="divide-y divide-navy-50">
            @forelse ($documents as $document)
                <tr>
                    <td class="table-td">{{ $document->title }}</td>
                    <td class="table-td font-mono text-xs">@if ($document->case)<a class="underline" href="{{ route('portal.cases.show', $document->case) }}">{{ $document->case->case_number }}</a>@endif</td>
                    <td class="table-td">{{ $document->category }}</td>
                    <td class="table-td"><span class="badge bg-navy-100">{{ $document->status }}</span></td>
                    <td class="table-td">{{ $document->virus_scan_status }}</td>
                    <td class="table-td">{{ $document->created_at->format('M j, Y') }}</td>
                    <td class="table-td space-x-2">
                        @can('documents.download')<a class="underline" href="{{ route('portal.documents.download', $document) }}">{{ __('Download') }}</a>@endcan
                        @if ($document->status === 'pending')
                            @can('documents.approve')
                                <form class="inline" method="post" action="{{ route('portal.documents.review', $document) }}">@csrf<input type="hidden" name="status" value="approved"><button class="underline">{{ __('Approve') }}</button></form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-td py-8 text-center text-navy-400">{{ __('No documents.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $documents->links() }}</div>
@endsection
