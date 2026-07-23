@extends('layouts.site')
@section('title', __('Terms of Use'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Terms of Use') }}</h1>
    <p class="mt-2 text-sm text-navy-500">{{ __('Last updated:') }} {{ \App\Services\Settings::get('legal_updated.terms', now()->format('F j, Y')) }}</p>
    <div class="prose-navy mt-8 space-y-5 text-lg leading-relaxed text-navy-700">

        <p>{{ __("By using this website you agree to these terms. The website provides general information and a way to contact :brand; it does not provide legal advice, and using it does not create an attorney-client or fiduciary relationship.", ["brand" => \App\Services\Settings::brand("name")]) }}</p>
        <p>{{ __("Nothing on this site is a promise that funds exist, that any claim will succeed, or that any recovery is guaranteed. Any engagement with us is governed solely by a separately signed written agreement.") }}</p>
        <p>{{ __("You agree to provide truthful information and not to misuse the site (including automated scraping, attempting to access other users\u{2019} data, or interfering with security features).") }}</p>
        <p>{{ __("The site is provided as-is without warranties; to the extent permitted by law, our liability arising out of website use is limited.") }}</p>
        <p class="rounded-md bg-gold-50 px-4 py-3 text-sm">{{ __("[ATTORNEY REVIEW REQUIRED] Terms must be finalized by counsel before public launch.") }}</p>
    </div>
</div>
@endsection
