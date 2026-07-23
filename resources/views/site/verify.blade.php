@extends('layouts.site')
@section('title', __('Verify a Letter From Us'))
@section('content')
<div class="mx-auto max-w-2xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Verify a Letter From Us') }}</h1>
    <p class="mt-4 text-lg leading-relaxed text-navy-700">
        {{ __('Enter the reference number from your letter, plus your last name and the property ZIP code. We will confirm whether the letter is genuine — nothing about your case is shown on this page.') }}
    </p>

    @isset($checked)
        @if ($result)
            <div class="card mt-8 border-l-4 border-l-gold-500">
                <h2 class="font-serif text-2xl font-bold text-navy-900">✓ {{ __('This reference number is authentic') }}</h2>
                <dl class="mt-4 space-y-2 text-lg">
                    <div><dt class="inline font-semibold">{{ __('Reference:') }}</dt> <dd class="inline font-mono">{{ $result['reference'] }}</dd></div>
                    <div><dt class="inline font-semibold">{{ __('Your case representative:') }}</dt> <dd class="inline">{{ $result['representative'] }}</dd></div>
                    @if (\App\Services\Settings::brand('phone'))
                        <div><dt class="inline font-semibold">{{ __('Call us at:') }}</dt> <dd class="inline">{{ \App\Services\Settings::brand('phone') }}</dd></div>
                    @endif
                </dl>
                <p class="mt-4 text-sm text-navy-600">
                    {{ __('Safest way to reach us: call the number above (also published on our Contact page) and ask for your representative by name. We will verify your identity before discussing any case details.') }}
                </p>
            </div>
        @else
            <div class="card mt-8 border-l-4 border-l-burgundy-600">
                <h2 class="font-serif text-2xl font-bold">{{ __('We could not verify that combination') }}</h2>
                <p class="mt-3 leading-relaxed text-navy-700">
                    {{ __('The reference number, last name, and ZIP code you entered did not match our records. Please double-check the letter. If it still does not verify, the letter may not be from us — see our Scam Awareness guide, and feel free to call us to confirm.') }}
                </p>
                <a href="{{ route('site.scam-awareness') }}" class="btn-outline mt-4">{{ __('Scam Awareness Guide') }}</a>
            </div>
        @endif
    @endisset

    <form method="post" action="{{ route('site.verify.check') }}" class="card mt-8 space-y-5">
        @csrf
        <div><label class="form-label" for="reference">{{ __('Reference number (from your letter)') }}</label>
            <input class="form-input font-mono" id="reference" name="reference" required placeholder="HCS-2026-MD-AA-000001" value="{{ old('reference') }}">
            @error('reference')<p class="form-error">{{ $message }}</p>@enderror</div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label class="form-label" for="last_name">{{ __('Your last name') }}</label>
                <input class="form-input" id="last_name" name="last_name" required value="{{ old('last_name') }}"></div>
            <div><label class="form-label" for="zip">{{ __('Property ZIP code') }}</label>
                <input class="form-input" id="zip" name="zip" required value="{{ old('zip') }}"></div>
        </div>
        <button class="btn-primary">{{ __('Verify') }}</button>
    </form>
</div>
@endsection
