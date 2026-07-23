<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('My Case')) — {{ \App\Services\Settings::brand('name') }}</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream font-sans text-navy-900 antialiased">
<header class="border-b border-navy-100 bg-white">
    <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4 sm:px-6">
        <a href="{{ route('client.dashboard') }}" class="flex items-center gap-3">
            <x-brand-mark class="h-9 w-9 text-navy-800" />
            <span class="font-serif text-lg font-bold">{{ \App\Services\Settings::brand('name') }}</span>
        </a>
        <nav class="flex items-center gap-4 text-sm" aria-label="{{ __('Account') }}">
            <a href="{{ route('client.dashboard') }}" class="hover:text-gold-700">{{ __('My Cases') }}</a>
            <a href="{{ route('client.profile') }}" class="hover:text-gold-700">{{ __('My Details') }}</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="underline">{{ __('Sign out') }}</button></form>
        </nav>
    </div>
</header>

@if (session('status'))
    <div class="mx-auto mt-4 max-w-4xl rounded-md border border-gold-200 bg-gold-50 px-4 py-3 text-sm">{{ session('status') }}</div>
@endif

<main class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    @yield('content')
</main>

<footer class="mt-16 border-t border-navy-100 bg-white py-6 text-center text-xs text-navy-400">
    <p class="mx-auto max-w-2xl px-4">{{ config('branding.disclaimer') }}</p>
</footer>
</body>
</html>
