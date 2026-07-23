@extends('layouts.site')
@section('title', __('Set Up Two-Factor Authentication'))
@section('content')
<div class="mx-auto max-w-md px-4 py-16">
    <div class="card">
        <h1 class="font-serif text-2xl font-bold">{{ __('Set Up Two-Factor Authentication') }}</h1>
        <p class="mt-2 text-sm text-navy-500">{{ __('Two-factor authentication is required for all staff accounts. Add this account to your authenticator app, then confirm with a 6-digit code.') }}</p>
        <div class="mt-6 rounded-md bg-navy-50 p-4 text-sm">
            <p class="font-semibold">{{ __('Setup key (enter manually in your authenticator app):') }}</p>
            <p class="mt-2 select-all break-all font-mono text-base">{{ $secret }}</p>
            <p class="mt-3 text-xs text-navy-500">{{ __('Or use this link on a device with an authenticator app:') }}</p>
            <p class="mt-1 select-all break-all font-mono text-xs">{{ $otpauthUrl }}</p>
        </div>
        <form method="post" action="{{ route('mfa.enroll.confirm') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="form-label" for="code">{{ __('6-digit code from your app') }}</label>
                <input class="form-input" type="text" inputmode="numeric" pattern="[0-9]{6}" id="code" name="code" required autocomplete="one-time-code" autofocus>
                @error('code')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Confirm & Enable') }}</button>
        </form>
    </div>
</div>
@endsection
