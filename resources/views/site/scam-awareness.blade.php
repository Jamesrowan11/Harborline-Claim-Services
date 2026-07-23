@extends('layouts.site')
@section('title', __('Scam Awareness & How to Verify Us'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Scam Awareness & How to Verify Us') }}</h1>
    <div class="mt-8 space-y-6 text-lg leading-relaxed text-navy-700">
        <p>{{ __('Sadly, real surplus funds attract real scammers. We would rather teach you to be skeptical — including of us — than see anyone taken advantage of.') }}</p>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __('Warning signs of a scam') }}</h2>
        <ul class="list-disc space-y-2 pl-6">
            <li>{{ __('Demands for upfront fees, gift cards, wire transfers, or cryptocurrency') }}</li>
            <li>{{ __('Requests for your Social Security number, bank login, or card details early on') }}</li>
            <li>{{ __('Claims to be from a court, sheriff, or government office (real ones do not cold-call about surplus funds and ask for money)') }}</li>
            <li>{{ __('"Guaranteed" money or pressure to sign immediately') }}</li>
            <li>{{ __('Refusal to put anything in writing or to let you consult an attorney') }}</li>
        </ul>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __('How to verify us') }}</h2>
        <ol class="list-decimal space-y-2 pl-6">
            <li>{{ __('Use the "Verify a Letter" page with the reference number on your letter.') }}</li>
            <li>{{ __('Call the phone number published on this website and ask for your case representative by name.') }}</li>
            <li>{{ __('Check our company registration with the state. Ask us for our legal entity details — we will provide them happily.') }}</li>
            <li>{{ __('Take your time. A legitimate opportunity does not evaporate because you spent a week checking it out.') }}</li>
        </ol>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __('What we will never do') }}</h2>
        <ul class="list-disc space-y-2 pl-6">
            <li>{{ __('Ask for money before any recovery') }}</li>
            <li>{{ __('Ask for banking credentials or card numbers') }}</li>
            <li>{{ __('Pose as a government office or court') }}</li>
            <li>{{ __('Guarantee that you will receive funds') }}</li>
        </ul>
    </div>
    <a href="{{ route('site.verify') }}" class="btn-primary mt-8">{{ __('Verify a Letter From Us') }}</a>
</div>
@endsection
