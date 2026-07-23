@extends('layouts.site')
@section('title', __('Privacy Policy'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Privacy Policy') }}</h1>
    <p class="mt-2 text-sm text-navy-500">{{ __('Last updated:') }} {{ \App\Services\Settings::get('legal_updated.privacy', now()->format('F j, Y')) }}</p>
    <div class="prose-navy mt-8 space-y-5 text-lg leading-relaxed text-navy-700">

        <p>{{ __("This Privacy Policy describes how :legal (\"we\") collects, uses, and protects personal information.", ["legal" => \App\Services\Settings::brand("legal_name")]) }}</p>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __("Information we collect") }}</h2>
        <p>{{ __("We collect information you provide through our forms (names, contact details, property information), information from lawful public records research, and limited technical data needed to operate the website securely (such as IP addresses in security logs).") }}</p>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __("What we never collect through public web forms") }}</h2>
        <p>{{ __("We do not request Social Security numbers, bank credentials, or payment-card details through public website forms.") }}</p>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __("How we use information") }}</h2>
        <p>{{ __("To research possible surplus funds, communicate with you as you have agreed, provide the client portal, meet legal obligations, and keep the service secure. We do not sell personal information.") }}</p>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __("Sharing") }}</h2>
        <p>{{ __("Information is shared only as needed to serve you: with independent licensed attorneys handling legal steps for your matter, with official funds holders as part of a claim you have authorized, with service providers under confidentiality obligations, and where required by law.") }}</p>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __("Your choices") }}</h2>
        <p>{{ __("You may ask us to correct your information, stop contacting you, or — where legally permitted — close your account and delete records. Some records must be retained under legal or regulatory obligations.") }}</p>
        <p class="rounded-md bg-gold-50 px-4 py-3 text-sm">{{ __("[ATTORNEY REVIEW REQUIRED] This policy is a working draft and must be reviewed by counsel for state-specific privacy obligations before public launch.") }}</p>
    </div>
</div>
@endsection
