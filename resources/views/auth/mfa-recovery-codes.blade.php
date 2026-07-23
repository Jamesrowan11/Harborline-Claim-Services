@extends('layouts.site')
@section('title', __('Recovery Codes'))
@section('content')
<div class="mx-auto max-w-md px-4 py-16">
    <div class="card">
        <h1 class="font-serif text-2xl font-bold">{{ __('Save Your Recovery Codes') }}</h1>
        <p class="mt-2 text-sm text-navy-500">{{ __('Store these codes somewhere safe. Each code can be used once if you lose access to your authenticator app. They will not be shown again.') }}</p>
        <ul class="mt-6 grid grid-cols-2 gap-2 font-mono text-sm">
            @foreach ($recoveryCodes as $code)
                <li class="rounded bg-navy-50 px-3 py-2">{{ $code }}</li>
            @endforeach
        </ul>
        <a href="{{ route('portal.dashboard') }}" class="btn-primary mt-6 w-full">{{ __('Continue') }}</a>
    </div>
</div>
@endsection
