@extends('layouts.site')
@section('title', __('Submitted for Preliminary Review'))
@section('content')
<div class="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6">
    <x-brand-mark class="mx-auto h-16 w-16 text-navy-800" />
    <h1 class="mt-6 font-serif text-4xl font-bold">{{ __('Submitted for Preliminary Review') }}</h1>
    <p class="mt-4 text-lg leading-relaxed text-navy-700">
        {{ __('Thank you, :name. Your inquiry has been received and assigned reference number', ['name' => $lead->first_name]) }}
        <span class="font-mono font-bold text-navy-900">{{ $lead->lead_number }}</span>.
    </p>
    <p class="mt-4 text-navy-700">{{ __('We will research public records and contact you by your preferred method. A confirmation email is on its way if you provided an email address.') }}</p>
    <p class="mt-6 rounded-md border border-gold-200 bg-gold-50 px-5 py-4 text-sm text-navy-800">
        {{ __('Please note: submitting an inquiry does not mean funds exist or that any recovery is guaranteed. We will tell you honestly what our research finds.') }}
    </p>
    <a href="{{ route('home') }}" class="btn-outline mt-8">{{ __('Return Home') }}</a>
</div>
@endsection
