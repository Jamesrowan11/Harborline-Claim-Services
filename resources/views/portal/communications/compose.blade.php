@extends('layouts.portal')
@section('title', __('Compose Communication'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Compose from Template') }}</h1>
<p class="mt-2 max-w-2xl text-sm text-navy-500">{{ __('Communications are always created as drafts, require approval, and pass the outreach compliance gate (approved template, consent, no opt-out, verification level, frequency limits, no holds) before delivery.') }}</p>
<form method="get" class="card mt-6 max-w-2xl space-y-4">
    <div><label class="form-label">{{ __('Case ID') }}</label><input class="form-input" type="number" name="case_id" value="{{ request('case_id') }}" required></div>
    <div><label class="form-label">{{ __('Template') }}</label>
        <select class="form-input" name="template_id">
            @foreach ($templates as $template)
                <option value="{{ $template->id }}" @selected(request('template_id') == $template->id)>
                    [{{ $template->category }}] {{ $template->name }} {{ $template->isApproved() ? '' : '(NOT APPROVED)' }}
                </option>
            @endforeach
        </select></div>
    <button class="btn-outline !py-2">{{ __('Preview') }}</button>
</form>
@if ($preview && $case)
    <div class="card mt-6 max-w-2xl">
        <h2 class="font-serif text-lg font-bold">{{ __('Preview for :case', ['case' => $case->case_number]) }}</h2>
        <p class="mt-2 text-sm font-semibold">{{ $preview['subject'] }}</p>
        <pre class="mt-2 whitespace-pre-wrap rounded bg-navy-50 p-4 font-sans text-sm">{{ $preview['body'] }}</pre>
        <form method="post" action="{{ route('portal.communications.store') }}" class="mt-4 flex items-end gap-3">
            @csrf
            <input type="hidden" name="case_id" value="{{ $case->id }}">
            <input type="hidden" name="template_id" value="{{ request('template_id') }}">
            <div><label class="form-label text-xs">{{ __('Channel') }}</label>
                <select class="form-input !py-1.5" name="channel">
                    <option value="email">email</option><option value="letter">letter</option><option value="sms">sms</option>
                </select></div>
            <button class="btn-primary !py-2">{{ __('Create Draft') }}</button>
        </form>
    </div>
@endif
@endsection
