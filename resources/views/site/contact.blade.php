@extends('layouts.site')
@section('title', __('Contact Us'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Contact Us') }}</h1>
    <div class="mt-6 space-y-2 text-lg text-navy-700">
        @if (\App\Services\Settings::brand('phone'))<p><span class="font-semibold">{{ __('Phone:') }}</span> {{ \App\Services\Settings::brand('phone') }}</p>@endif
        @if (\App\Services\Settings::brand('email'))<p><span class="font-semibold">{{ __('Email:') }}</span> {{ \App\Services\Settings::brand('email') }}</p>@endif
        @if (\App\Services\Settings::brand('address'))<p><span class="font-semibold">{{ __('Mail:') }}</span> {{ \App\Services\Settings::brand('address') }}</p>@endif
    </div>
    <p class="mt-4 text-navy-600">{{ __('Please do not send personal identification documents by email. Use the secure portal or ask us about safe options.') }}</p>
    <form method="post" action="{{ route('site.contact.submit') }}" class="card mt-8 space-y-5">
        @csrf
        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div>
            <label class="form-label" for="name">{{ __('Your name') }}</label>
            <input class="form-input" id="name" name="name" required value="{{ old('name') }}">
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="form-label" for="email">{{ __('Email') }}</label>
                <input class="form-input" type="email" id="email" name="email" required value="{{ old('email') }}">
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="phone">{{ __('Phone (optional)') }}</label>
                <input class="form-input" id="phone" name="phone" value="{{ old('phone') }}">
            </div>
        </div>
        <div>
            <label class="form-label" for="message">{{ __('How can we help?') }}</label>
            <textarea class="form-input" id="message" name="message" rows="5" required>{{ old('message') }}</textarea>
            @error('message')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary">{{ __('Send Message') }}</button>
    </form>
</div>
@endsection
