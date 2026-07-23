@extends('layouts.site')
@section('title', __('Choose a New Password'))
@section('content')
<div class="mx-auto max-w-md px-4 py-16">
    <div class="card">
        <h1 class="font-serif text-2xl font-bold">{{ __('Choose a New Password') }}</h1>
        <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="form-label" for="email">{{ __('Email address') }}</label>
                <input class="form-input" type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="password">{{ __('New password (12+ characters, letters and numbers)') }}</label>
                <input class="form-input" type="password" id="password" name="password" required autocomplete="new-password">
                @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                <input class="form-input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Reset Password') }}</button>
        </form>
    </div>
</div>
@endsection
