@extends('layouts.site')
@section('title', __('Why We Contacted You'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Why We Contacted You') }}</h1>
    <div class="mt-8 space-y-6 text-lg leading-relaxed text-navy-700">
        <p>{{ __('If you received a letter or call from us, it is because our research of public records suggested that you — or someone you may represent or be related to — could have a possible claim to funds remaining after a property sale.') }}</p>
        <p>{{ __('That word "possible" matters. At the time we first reach out, we may not yet have final confirmation from the funds holder, and we will never tell you money is guaranteed.') }}</p>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __('What we will never ask for in a first contact') }}</h2>
        <ul class="list-disc space-y-2 pl-6">
            <li>{{ __('Your Social Security number') }}</li>
            <li>{{ __('Bank account or online banking details') }}</li>
            <li>{{ __('Credit or debit card numbers') }}</li>
            <li>{{ __('Any upfront payment') }}</li>
        </ul>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __('What you can do next') }}</h2>
        <ol class="list-decimal space-y-2 pl-6">
            <li>{{ __('Verify the letter is really from us using the reference number on it.') }}</li>
            <li>{{ __('Call us back using the number published on this website — not just the one on the letter, if that gives you more confidence.') }}</li>
            <li>{{ __('Ask us anything. There is no obligation, and no cost to talk.') }}</li>
            <li>{{ __('If you prefer we never contact you again, tell us once and we will stop.') }}</li>
        </ol>
    </div>
    <div class="mt-8 flex flex-wrap gap-4">
        <a href="{{ route('site.verify') }}" class="btn-primary">{{ __('Verify a Letter') }}</a>
        <a href="{{ route('site.scam-awareness') }}" class="btn-outline">{{ __('Scam Awareness Guide') }}</a>
    </div>
    <div class="mt-10"><x-independence-disclaimer /></div>
</div>
@endsection
