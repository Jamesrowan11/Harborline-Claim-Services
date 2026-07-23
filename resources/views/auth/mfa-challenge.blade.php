@extends('layouts.site')
@section('title', __('Two-Factor Check'))
@section('content')
<div class="mx-auto max-w-md px-4 py-16">
    <div class="card">
        <h1 class="font-serif text-2xl font-bold">{{ __('Two-Factor Check') }}</h1>
        <p class="mt-2 text-sm text-navy-500">{{ __('Enter the 6-digit code from your authenticator app, or one of your recovery codes.') }}</p>
        <form method="post" action="{{ route('mfa.verify') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="form-label" for="code">{{ __('Code') }}</label>
                <input class="form-input" type="text" id="code" name="code" required autocomplete="one-time-code" autofocus>
                @error('code')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Verify') }}</button>
        </form>
    </div>
</div>
@endsection
