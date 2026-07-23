@extends('layouts.site')
@section('title', __('Legal Disclaimer'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Legal Disclaimer') }}</h1>
    <p class="mt-2 text-sm text-navy-500">{{ __('Last updated:') }} {{ \App\Services\Settings::get('legal_updated.disclaimer', now()->format('F j, Y')) }}</p>
    <div class="prose-navy mt-8 space-y-5 text-lg leading-relaxed text-navy-700">

        <p>{{ config("branding.disclaimer") }}</p>
        <p>{{ __("All references to surplus funds on this website describe possibilities that require independent verification with the official funds holder. Laws, deadlines, procedures, and eligibility vary by state, county, and sale type; nothing here describes the law applicable to your specific situation.") }}</p>
        <p>{{ __("We are not a law firm and do not give legal advice. Where legal work is required, matters are referred to independent licensed attorneys who represent you under their own professional obligations.") }}</p>
        <p>{{ __("We are not a debt collector and this website is not an attempt to collect a debt.") }}</p>
    </div>
</div>
@endsection
