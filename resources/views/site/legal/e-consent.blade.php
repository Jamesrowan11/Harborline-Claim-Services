@extends('layouts.site')
@section('title', __('Electronic Communications Consent'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Electronic Communications Consent') }}</h1>
    <p class="mt-2 text-sm text-navy-500">{{ __('Last updated:') }} {{ \App\Services\Settings::get('legal_updated.e-consent', now()->format('F j, Y')) }}</p>
    <div class="prose-navy mt-8 space-y-5 text-lg leading-relaxed text-navy-700">

        <p>{{ __("By giving electronic-communications consent, you agree that :brand may deliver communications, records, and disclosures to you electronically — by email and through the secure client portal — instead of on paper.", ["brand" => \App\Services\Settings::brand("name")]) }}</p>
        <p>{{ __("You may withdraw this consent at any time by contacting us or updating your preferences in the client portal; we will then use postal mail. Withdrawing consent does not affect the validity of communications sent before withdrawal.") }}</p>
        <p>{{ __("To receive electronic communications you need a working email address and a device with internet access and a current web browser. Keep your email address up to date with us.") }}</p>
        <p>{{ __("You may request a free paper copy of any electronic record we have sent you.") }}</p>
    </div>
</div>
@endsection
