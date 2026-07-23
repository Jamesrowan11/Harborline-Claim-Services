@extends('layouts.site')
@section('title', __('Frequently Asked Questions'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Frequently Asked Questions') }}</h1>
    <div class="mt-8 space-y-4">
        @foreach ([
            [__('Is Harborline a government agency, court, or law firm?'), __('No. We are an independent, privately owned research and assistance company. We are not a government agency, court, county office, trustee, or law firm, and we are not affiliated with any of them.')],
            [__('How do you know about my former property?'), __('Foreclosure and auction sales create public records — court dockets, county land records, published sale lists. Our research starts and ends with lawful public sources.')],
            [__('Are you saying I am owed money?'), __('No. We only ever discuss possible funds until the official funds holder verifies a claim. Anyone who promises you guaranteed money before verification should not be trusted.')],
            [__('What does it cost?'), __('Preliminary research and questions cost nothing. If verified funds appear claimable and you choose to proceed, any compensation would be described in a written agreement you review in advance. Fee arrangements are reviewed by counsel and comply with applicable state rules.')],
            [__('Do I need my own lawyer?'), __('You are always free to hire your own attorney, and we encourage independent review of any agreement. Where a claim requires legal work, it is handled by independent licensed attorneys.')],
            [__('What information will you ask me for?'), __('Initially, only basics: names, the property address, and how to reach you. We never ask for Social Security numbers, bank logins, or card details through our website forms. Identity documents, when eventually needed, go through the secure portal only.')],
            [__('How long does the process take?'), __('It varies widely by county, court, and case type — from a few months to much longer. We will always tell you the honest status rather than an optimistic guess.')],
            [__('What if the owner has passed away?'), __('Claims involving estates and heirs are common but legally more involved. These situations are referred to licensed attorneys for the legal steps.')],
            [__('Can I make you stop contacting me?'), __('Yes, at any time — one request is enough. Every message we send includes a way to opt out, and our systems enforce it.')],
            [__('How do I know a letter is really from you?'), __('Use the "Verify a Letter" page. Enter the reference number, your last name, and the property ZIP code, and we will confirm whether it is genuine — without exposing any of your information.')],
        ] as [$q, $a])
            <details class="card group">
                <summary class="cursor-pointer font-serif text-lg font-bold marker:content-none">{{ $q }}</summary>
                <p class="mt-3 leading-relaxed text-navy-600">{{ $a }}</p>
            </details>
        @endforeach
    </div>
</div>
@endsection
