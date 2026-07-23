@extends('layouts.site')
@section('title', __('SMS Terms'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('SMS Terms') }}</h1>
    <p class="mt-2 text-sm text-navy-500">{{ __('Last updated:') }} {{ \App\Services\Settings::get('legal_updated.sms-terms', now()->format('F j, Y')) }}</p>
    <div class="prose-navy mt-8 space-y-5 text-lg leading-relaxed text-navy-700">

        <p>{{ __("Text messaging is entirely optional and is never required to work with us.") }}</p>
        <p>{{ __("If you opt in, :brand may send occasional text messages about your inquiry or case — for example, status updates or document reminders. Message frequency varies; message and data rates may apply.", ["brand" => \App\Services\Settings::brand("name")]) }}</p>
        <p>{{ __("Reply STOP at any time to cancel, or HELP for help. You can also withdraw SMS consent in the client portal or by contacting us. Opting out of SMS does not affect your case.") }}</p>
        <p>{{ __("We never send marketing blasts, and we never ask for sensitive information (such as Social Security or bank numbers) by text.") }}</p>
        <p class="rounded-md bg-gold-50 px-4 py-3 text-sm">{{ __("[ATTORNEY REVIEW REQUIRED] SMS program terms must be reviewed for TCPA and carrier-compliance requirements before SMS is enabled.") }}</p>
    </div>
</div>
@endsection
