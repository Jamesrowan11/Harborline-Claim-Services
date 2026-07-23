@extends('layouts.portal')
@section('title', __('Templates'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Document & Communication Templates') }}</h1>
<p class="mt-2 max-w-2xl text-sm text-navy-500">{{ __('Merge fields: @{{claimant.name}}, @{{case.number}}, @{{case.status_label}}, @{{case.manager}}, @{{brand.name}}, @{{brand.phone}}, @{{brand.disclaimer}}, @{{today}}. Editing any template returns it to draft and requires re-approval before it can be sent.') }}</p>
<div class="mt-6 space-y-4">
    @foreach ($templates as $template)
        <details class="card">
            <summary class="flex cursor-pointer flex-wrap items-center gap-2">
                <span class="font-serif font-bold">{{ $template->name }}</span>
                <span class="badge bg-navy-100">{{ $template->category }}</span>
                <span class="badge {{ $template->isApproved() ? 'bg-gold-100 text-gold-800' : 'bg-burgundy-500/15 text-burgundy-700' }}">{{ $template->approval_status }} v{{ $template->version }}</span>
                @if ($template->requires_attorney_review)<span class="badge bg-gold-100 text-gold-800">{{ __('ATTORNEY REVIEW REQUIRED') }}</span>@endif
            </summary>
            <form method="post" action="{{ route('portal.admin.templates.update', $template) }}" class="mt-4 space-y-3">
                @csrf
                <div><label class="form-label">{{ __('Subject') }}</label><input class="form-input" name="subject" value="{{ $template->subject }}"></div>
                <div><label class="form-label">{{ __('Body') }}</label><textarea class="form-input font-mono text-sm" name="body" rows="10">{{ $template->body }}</textarea></div>
                <div class="flex gap-2">
                    <button class="btn-outline !py-2">{{ __('Save New Version') }}</button>
                    @can('templates.approve')
                        @if (! $template->isApproved())
                            <button form="approve-{{ $template->id }}" class="btn-primary !py-2">{{ __('Approve for Use') }}</button>
                        @endif
                    @endcan
                </div>
            </form>
            <form id="approve-{{ $template->id }}" method="post" action="{{ route('portal.admin.templates.approve', $template) }}">@csrf</form>
        </details>
    @endforeach
</div>
@endsection
