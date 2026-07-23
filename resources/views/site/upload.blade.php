@extends('layouts.site')
@section('title', __('Upload Requested Documents'))
@section('content')
<div class="mx-auto max-w-2xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Upload Requested Documents') }}</h1>
    <p class="mt-4 text-lg leading-relaxed text-navy-700">
        {{ __('If a member of our team asked you to send documents, use this secure page — never regular email. Files are encrypted in transit and reviewed only by authorized staff.') }}
    </p>
    <p class="mt-3 text-sm text-navy-600">{{ __('Have a portal account? Signing in gives you the full picture of your case.') }} <a href="{{ route('login') }}" class="underline">{{ __('Client Login') }}</a></p>

    @isset($uploaded)
        <div class="card mt-8 border-l-4 border-l-gold-500">
            <h2 class="font-serif text-xl font-bold">✓ {{ __('Document received') }}</h2>
            <p class="mt-2 text-navy-700">{{ __('Thank you. Your document has been uploaded securely to file :ref and is pending review. You may upload another below.', ['ref' => $reference]) }}</p>
        </div>
    @endisset

    @empty($verified)
        <form method="post" action="{{ route('site.upload.verify') }}" class="card mt-8 space-y-5">
            @csrf
            <p class="text-sm text-navy-600">{{ __('First, confirm your identity with the details from our letter or email:') }}</p>
            <div><label class="form-label" for="reference">{{ __('Reference number') }}</label>
                <input class="form-input font-mono" id="reference" name="reference" required>
                @error('reference')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label class="form-label" for="last_name">{{ __('Your last name') }}</label>
                    <input class="form-input" id="last_name" name="last_name" required></div>
                <div><label class="form-label" for="zip">{{ __('Property ZIP code') }}</label>
                    <input class="form-input" id="zip" name="zip" required></div>
            </div>
            <button class="btn-primary">{{ __('Continue') }}</button>
        </form>
    @else
        <form method="post" action="{{ route('site.upload.store') }}" enctype="multipart/form-data" class="card mt-8 space-y-5">
            @csrf
            <p class="rounded bg-gold-50 px-3 py-2 text-sm">{{ __('Uploading to file :ref', ['ref' => $reference]) }}</p>
            <div><label class="form-label" for="title">{{ __('What is this document?') }}</label>
                <input class="form-input" id="title" name="title" required placeholder="{{ __('e.g. Copy of my photo ID') }}">
                @error('title')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="file">{{ __('Choose file (PDF, JPG, PNG, or Word — up to :mb MB)', ['mb' => config('security.max_upload_mb')]) }}</label>
                <input class="form-input" type="file" id="file" name="file" required>
                @error('file')<p class="form-error">{{ $message }}</p>@enderror</div>
            <button class="btn-primary">{{ __('Upload Securely') }}</button>
        </form>
    @endempty
</div>
@endsection
