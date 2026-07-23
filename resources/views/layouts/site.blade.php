<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', \App\Services\Settings::brand('tagline'))&nbsp;&mdash; {{ \App\Services\Settings::brand('name') }}</title>
    <meta name="description" content="@yield('meta_description', \App\Services\Settings::brand('name').' researches public records and helps potential claimants understand the administrative process for possible surplus funds after certain property sales. Not a government agency or law firm. Recovery is not guaranteed.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream font-sans text-navy-900 antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow">
    {{ __('Skip to main content') }}
</a>

<header class="border-b border-navy-100 bg-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <x-brand-mark class="h-11 w-11 text-navy-800" />
            <span class="leading-tight">
                <span class="block font-serif text-xl font-bold text-navy-900">{{ \App\Services\Settings::brand('name') }}</span>
                <span class="block text-xs text-navy-400">{{ __('Independent research assistance — not a government agency or law firm') }}</span>
            </span>
        </a>
        <nav aria-label="{{ __('Main navigation') }}" x-data="{ open: false }" class="text-base">
            <button @click="open = !open" class="rounded-md border border-navy-200 p-2 lg:hidden" :aria-expanded="open" aria-label="{{ __('Toggle menu') }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="hidden items-center gap-6 lg:flex" :class="{ 'hidden': false }">
                <a href="{{ route('site.how-it-works') }}" class="hover:text-gold-700">{{ __('How It Works') }}</a>
                <a href="{{ route('site.process') }}" class="hover:text-gold-700">{{ __('Our Process') }}</a>
                <a href="{{ route('site.faq') }}" class="hover:text-gold-700">{{ __('FAQ') }}</a>
                <a href="{{ route('site.about') }}" class="hover:text-gold-700">{{ __('About') }}</a>
                <a href="{{ route('site.verify') }}" class="hover:text-gold-700">{{ __('Verify a Letter') }}</a>
                <a href="{{ route('site.check') }}" class="btn-gold !py-2">{{ __('Check for Possible Funds') }}</a>
                <a href="{{ route('login') }}" class="btn-outline !py-2">{{ __('Client Login') }}</a>
            </div>
            <div x-show="open" x-cloak @click.outside="open = false" class="absolute inset-x-0 top-20 z-40 border-b border-navy-100 bg-white px-6 py-4 shadow-lg lg:hidden">
                <div class="flex flex-col gap-4">
                    <a href="{{ route('site.how-it-works') }}">{{ __('How Surplus Funds Work') }}</a>
                    <a href="{{ route('site.process') }}">{{ __('Our Process') }}</a>
                    <a href="{{ route('site.check') }}">{{ __('Check for Possible Funds') }}</a>
                    <a href="{{ route('site.faq') }}">{{ __('Frequently Asked Questions') }}</a>
                    <a href="{{ route('site.about') }}">{{ __('About') }}</a>
                    <a href="{{ route('site.why-contacted') }}">{{ __('Why We Contacted You') }}</a>
                    <a href="{{ route('site.verify') }}">{{ __('Verify a Letter From Us') }}</a>
                    <a href="{{ route('site.schedule') }}">{{ __('Schedule a Call') }}</a>
                    <a href="{{ route('site.contact') }}">{{ __('Contact') }}</a>
                    <a href="{{ route('login') }}" class="font-semibold">{{ __('Client Login') }}</a>
                </div>
            </div>
        </nav>
    </div>
</header>

<main id="main">
    @yield('content')
</main>

<footer class="mt-16 bg-navy-900 text-navy-100">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div class="grid gap-10 md:grid-cols-4">
            <div class="md:col-span-2">
                <div class="flex items-center gap-3">
                    <x-brand-mark class="h-9 w-9 text-white" />
                    <span class="font-serif text-lg font-bold text-white">{{ \App\Services\Settings::brand('name') }}</span>
                </div>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-navy-200">{{ config('branding.disclaimer') }}</p>
                @if (\App\Services\Settings::brand('parent_company'))
                    <p class="mt-3 text-xs text-navy-300">{{ __('A service of :parent', ['parent' => \App\Services\Settings::brand('parent_company')]) }}</p>
                @endif
            </div>
            <nav aria-label="{{ __('Learn') }}">
                <h2 class="font-serif text-sm font-bold uppercase tracking-wider text-gold-300">{{ __('Learn') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('site.how-it-works') }}" class="hover:text-gold-300">{{ __('How Surplus Funds Work') }}</a></li>
                    <li><a href="{{ route('site.process') }}" class="hover:text-gold-300">{{ __('Our Process') }}</a></li>
                    <li><a href="{{ route('site.faq') }}" class="hover:text-gold-300">{{ __('FAQ') }}</a></li>
                    <li><a href="{{ route('site.scam-awareness') }}" class="hover:text-gold-300">{{ __('Scam Awareness & How to Verify Us') }}</a></li>
                    <li><a href="{{ route('site.document-security') }}" class="hover:text-gold-300">{{ __('Document Security') }}</a></li>
                    <li><a href="{{ route('site.referrals') }}" class="hover:text-gold-300">{{ __('Professional & Attorney Referrals') }}</a></li>
                </ul>
            </nav>
            <nav aria-label="{{ __('Legal') }}">
                <h2 class="font-serif text-sm font-bold uppercase tracking-wider text-gold-300">{{ __('Legal') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('site.privacy') }}" class="hover:text-gold-300">{{ __('Privacy Policy') }}</a></li>
                    <li><a href="{{ route('site.terms') }}" class="hover:text-gold-300">{{ __('Terms of Use') }}</a></li>
                    <li><a href="{{ route('site.disclaimer') }}" class="hover:text-gold-300">{{ __('Legal Disclaimer') }}</a></li>
                    <li><a href="{{ route('site.e-consent') }}" class="hover:text-gold-300">{{ __('Electronic Communications Consent') }}</a></li>
                    <li><a href="{{ route('site.sms-terms') }}" class="hover:text-gold-300">{{ __('SMS Terms') }}</a></li>
                    <li><a href="{{ route('site.accessibility') }}" class="hover:text-gold-300">{{ __('Accessibility Statement') }}</a></li>
                </ul>
            </nav>
        </div>
        <p class="mt-10 border-t border-navy-700 pt-6 text-xs text-navy-300">
            &copy; {{ date('Y') }} {{ \App\Services\Settings::brand('legal_name') }}. {{ __('All rights reserved.') }}
            {{ __('Recovery is not guaranteed. Nothing on this website is legal advice.') }}
        </p>
    </div>
</footer>
</body>
</html>
