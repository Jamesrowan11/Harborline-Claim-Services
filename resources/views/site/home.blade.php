@extends('layouts.site')
@section('title', __('Possible Surplus Funds May Still Belong to You'))
@section('content')

{{-- Hero --}}
<section class="bg-navy-900 text-white">
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24">
        <div>
            <p class="font-serif text-sm uppercase tracking-widest text-gold-300">{{ \App\Services\Settings::brand('tagline') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-bold leading-tight sm:text-5xl">{{ __('Possible Surplus Funds May Still Belong to You') }}</h1>
            <p class="mt-6 max-w-xl text-lg leading-relaxed text-navy-100">
                {{ __('When certain properties are sold through foreclosure or another qualifying sale, money may remain after taxes, debts, liens, and approved expenses are paid. :brand researches public records and helps potential claimants understand the administrative recovery process.', ['brand' => \App\Services\Settings::brand('name')]) }}
            </p>
            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ route('site.check') }}" class="btn-gold">{{ __('Check for Possible Funds') }}</a>
                <a href="{{ route('site.verify') }}" class="btn-outline !border-white !text-white hover:!bg-navy-800">{{ __('Verify a Letter From Us') }}</a>
                <a href="{{ route('login') }}" class="btn-outline !border-navy-300 !text-navy-100 hover:!bg-navy-800">{{ __('Client Login') }}</a>
            </div>
        </div>
        <div class="hidden lg:block" aria-hidden="true">
            {{-- Abstract Chesapeake shoreline illustration --}}
            <svg viewBox="0 0 400 300" class="w-full" fill="none">
                <path d="M0 220 Q60 200 120 218 T240 216 T400 214 L400 300 L0 300 Z" fill="#223351"/>
                <path d="M0 240 Q80 222 160 238 T320 236 T400 238 L400 300 L0 300 Z" fill="#b3924f" opacity="0.35"/>
                <circle cx="290" cy="90" r="46" stroke="#b3924f" stroke-width="3"/>
                <path d="M290 52 L300 90 L290 82 L280 90 Z" fill="#b3924f"/>
                <path d="M290 128 L280 90 L290 98 L300 90 Z" fill="#faf7f2"/>
                <path d="M40 120 h70 v70 h-70 Z" stroke="#8b9cbe" stroke-width="3"/>
                <path d="M30 120 L75 88 L120 120" stroke="#8b9cbe" stroke-width="3" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>
</section>

{{-- Disclaimer near first CTA --}}
<section class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <x-independence-disclaimer />
</section>

{{-- Plain-English explanation --}}
<section class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
    <h2 class="font-serif text-3xl font-bold">{{ __('What this is, in plain English') }}</h2>
    <div class="mt-5 space-y-4 text-lg leading-relaxed text-navy-700">
        <p>{{ __('Sometimes, when a property is sold at a foreclosure, tax-related, judicial, sheriff, or trustee sale, the sale brings in more money than what was owed. After the debts, taxes, and approved costs are paid, the amount left over is often called a "surplus" or "excess proceeds."') }}</p>
        <p>{{ __('Those leftover funds do not automatically go to the former owner. They usually sit with a court, county office, trustee, or other funds holder until someone with a legal right to them files the proper paperwork.') }}</p>
        <p>{{ __('Our work is research and paperwork assistance: we study public records, try to identify people who may have a claim, explain the administrative process, and — where appropriate — connect people with independent licensed attorneys.') }}</p>
    </div>
</section>

{{-- How the process works --}}
<section class="bg-white py-14">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <h2 class="font-serif text-3xl font-bold">{{ __('How the process works') }}</h2>
        <div class="mt-8 grid gap-6 md:grid-cols-4">
            @foreach ([
                [__('Research'), __('We review public sale records, court files, and county lists to find possible remaining funds.')],
                [__('Verification'), __('We work to confirm with the official funds holder whether money actually remains and who may claim it.')],
                [__('Documentation'), __('If you choose to proceed, we help gather the documents the funds holder requires.')],
                [__('Review & Filing'), __('Legal steps are referred to independent licensed attorneys. Decisions are always made by the official funds holder — never by us.')],
            ] as $i => [$title, $text])
                <div class="card">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-navy-800 font-serif text-lg font-bold text-gold-300">{{ $i + 1 }}</span>
                    <h3 class="mt-4 font-serif text-xl font-bold">{{ $title }}</h3>
                    <p class="mt-2 leading-relaxed text-navy-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
        <p class="mt-6"><a href="{{ route('site.process') }}" class="font-semibold text-navy-800 underline">{{ __('Read about our full process') }} →</a></p>
    </div>
</section>

{{-- Why someone may not know --}}
<section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
    <h2 class="font-serif text-3xl font-bold">{{ __('Why you might not know these funds exist') }}</h2>
    <ul class="mt-6 space-y-4 text-lg leading-relaxed text-navy-700">
        <li class="flex gap-3"><span class="text-gold-600" aria-hidden="true">◆</span>{{ __('Notices are often mailed to the address of the property that was sold — an address the former owner has usually left.') }}</li>
        <li class="flex gap-3"><span class="text-gold-600" aria-hidden="true">◆</span>{{ __('The rules and deadlines vary by state and county, and the paperwork can be confusing.') }}</li>
        <li class="flex gap-3"><span class="text-gold-600" aria-hidden="true">◆</span>{{ __('When an owner has passed away, family members may not realize a claim could exist for the estate or heirs.') }}</li>
        <li class="flex gap-3"><span class="text-gold-600" aria-hidden="true">◆</span>{{ __('Funds can sit unclaimed for years, and in some places they may eventually be transferred or become harder to recover.') }}</li>
    </ul>
</section>

{{-- Verify authenticity --}}
<section class="bg-navy-800 py-14 text-white">
    <div class="mx-auto max-w-4xl px-4 sm:px-6">
        <h2 class="font-serif text-3xl font-bold">{{ __('Received a letter from us? Verify it.') }}</h2>
        <p class="mt-4 text-lg leading-relaxed text-navy-100">
            {{ __('We know unsolicited letters about money can feel suspicious — that caution is healthy. Every genuine letter from us includes a reference number. You can confirm it is really from us in under a minute, without sharing any sensitive information.') }}
        </p>
        <div class="mt-6 flex flex-wrap gap-4">
            <a href="{{ route('site.verify') }}" class="btn-gold">{{ __('Verify a Letter') }}</a>
            <a href="{{ route('site.scam-awareness') }}" class="btn-outline !border-white !text-white hover:!bg-navy-700">{{ __('Scam Awareness Guide') }}</a>
        </div>
    </div>
</section>

{{-- Compliance-first + portal benefits --}}
<section class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2">
    <div class="card">
        <h2 class="font-serif text-2xl font-bold">{{ __('Our compliance-first approach') }}</h2>
        <ul class="mt-4 space-y-3 leading-relaxed text-navy-700">
            <li>{{ __('We never claim funds definitely exist before verification.') }}</li>
            <li>{{ __('We never guarantee recovery — no honest company can.') }}</li>
            <li>{{ __('We never ask for Social Security numbers, bank logins, or payment card details through our website forms.') }}</li>
            <li>{{ __('Legal questions go to independent licensed attorneys.') }}</li>
            <li>{{ __('You can tell us to stop contacting you at any time.') }}</li>
        </ul>
    </div>
    <div class="card">
        <h2 class="font-serif text-2xl font-bold">{{ __('Your secure client portal') }}</h2>
        <ul class="mt-4 space-y-3 leading-relaxed text-navy-700">
            <li>{{ __('See the current status of your case in plain language.') }}</li>
            <li>{{ __('Upload requested documents safely — never by regular email.') }}</li>
            <li>{{ __('Message your case manager directly.') }}</li>
            <li>{{ __('Review and sign approved agreements electronically.') }}</li>
            <li>{{ __('Update your contact details and communication preferences.') }}</li>
        </ul>
        <a href="{{ route('login') }}" class="btn-primary mt-6">{{ __('Client Login') }}</a>
    </div>
</section>

{{-- FAQ preview --}}
<section class="bg-white py-14">
    <div class="mx-auto max-w-4xl px-4 sm:px-6">
        <h2 class="font-serif text-3xl font-bold">{{ __('Common questions') }}</h2>
        <div class="mt-6 space-y-4">
            @foreach ([
                [__('Is this a government program?'), __('No. We are an independent, privately owned research company. We are not affiliated with any court, county, or government agency.')],
                [__('Does contacting you cost anything?'), __('No. Checking whether possible funds exist and asking us questions is free. Any fee arrangement would be in a written agreement you review first — with time to ask questions and consult an attorney.')],
                [__('Are you saying I am owed money?'), __('No. Until the official funds holder verifies a claim, nobody can honestly promise that. We talk about "possible" funds for exactly that reason.')],
            ] as [$q, $a])
                <details class="card group">
                    <summary class="cursor-pointer font-serif text-lg font-bold marker:content-none">{{ $q }}</summary>
                    <p class="mt-3 leading-relaxed text-navy-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
        <p class="mt-6"><a href="{{ route('site.faq') }}" class="font-semibold text-navy-800 underline">{{ __('See all frequently asked questions') }} →</a></p>
    </div>
</section>

{{-- Contact / intake --}}
<section class="mx-auto max-w-4xl px-4 py-14 text-center sm:px-6">
    <h2 class="font-serif text-3xl font-bold">{{ __('Wondering if funds might be waiting?') }}</h2>
    <p class="mx-auto mt-4 max-w-2xl text-lg leading-relaxed text-navy-700">
        {{ __('Share a few details about the property and we will research public records at no cost. We will only follow up in the way you choose.') }}
    </p>
    <div class="mt-8 flex flex-wrap justify-center gap-4">
        <a href="{{ route('site.check') }}" class="btn-primary">{{ __('Check for Possible Funds') }}</a>
        <a href="{{ route('site.schedule') }}" class="btn-outline">{{ __('Schedule a Call') }}</a>
        <a href="{{ route('site.contact') }}" class="btn-outline">{{ __('Contact Us') }}</a>
    </div>
</section>
@endsection
