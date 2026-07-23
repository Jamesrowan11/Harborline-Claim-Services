@extends('layouts.site')
@section('title', __('Reset Password'))
@section('content')
<div class="mx-auto max-w-md px-4 py-16">
    <div class="card">
        <h1 class="font-serif text-2xl font-bold">{{ __('Reset Your Password') }}</h1>
        <p class="mt-2 text-sm text-navy-500">{{ __('Enter your email address and we will send a secure reset link.') }}</p>
        @if (session('status'))
            <p class="mt-4 rounded bg-gold-50 px-3 py-2 text-sm">{{ session('status') }}</p>
        @endif
        <form method="post" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="form-label" for="email">{{ __('Email address') }}</label>
                <input class="form-input" type="email" id="email" name="email" required autocomplete="email" autofocus>
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Send Reset Link') }}</button>
        </form>
    </div>
</div>
@endsection
