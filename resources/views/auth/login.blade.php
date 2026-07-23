@extends('layouts.site')
@section('title', __('Sign In'))
@section('content')
<div class="mx-auto max-w-md px-4 py-16">
    <div class="card">
        <h1 class="font-serif text-2xl font-bold">{{ __('Sign In') }}</h1>
        <p class="mt-2 text-sm text-navy-500">{{ __('Client and staff sign-in for :name.', ['name' => \App\Services\Settings::brand('name')]) }}</p>
        @if (session('status'))
            <p class="mt-4 rounded bg-gold-50 px-3 py-2 text-sm">{{ session('status') }}</p>
        @endif
        <form method="post" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="form-label" for="email">{{ __('Email address') }}</label>
                <input class="form-input" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="password">{{ __('Password') }}</label>
                <input class="form-input" type="password" id="password" name="password" required autocomplete="current-password">
                @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Sign In') }}</button>
        </form>
        <p class="mt-4 text-center text-sm"><a class="underline" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a></p>
    </div>
</div>
@endsection
