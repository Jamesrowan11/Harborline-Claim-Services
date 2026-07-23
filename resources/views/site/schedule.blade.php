@extends('layouts.site')
@section('title', __('Schedule a Call'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Schedule a Call') }}</h1>
    <p class="mt-4 text-lg leading-relaxed text-navy-700">
        {{ __('Prefer to talk it through? Send us a note with the times that suit you, and a member of our team will call you back. Calls are unhurried, free, and carry no obligation.') }}
    </p>
    @if (\App\Services\Settings::brand('phone'))
        <p class="mt-6 text-lg">{{ __('You can also call us directly at') }} <span class="font-bold">{{ \App\Services\Settings::brand('phone') }}</span>.</p>
    @endif
    <form method="post" action="{{ route('site.contact.submit') }}" class="card mt-8 space-y-5">
        @csrf
        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div>
            <label class="form-label" for="name">{{ __('Your name') }}</label>
            <input class="form-input" id="name" name="name" required>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="form-label" for="email">{{ __('Email') }}</label>
                <input class="form-input" type="email" id="email" name="email" required>
            </div>
            <div>
                <label class="form-label" for="phone">{{ __('Phone number for the call') }}</label>
                <input class="form-input" id="phone" name="phone">
            </div>
        </div>
        <div>
            <label class="form-label" for="message">{{ __('Good days and times to reach you') }}</label>
            <textarea class="form-input" id="message" name="message" rows="4" required placeholder="{{ __('For example: weekday mornings, or Tuesday after 3pm') }}"></textarea>
        </div>
        @error('message')<p class="form-error">{{ $message }}</p>@enderror
        <button class="btn-primary">{{ __('Request a Call Back') }}</button>
    </form>
</div>
@endsection
