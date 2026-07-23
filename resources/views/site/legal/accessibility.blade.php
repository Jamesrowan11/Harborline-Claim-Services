@extends('layouts.site')
@section('title', __('Accessibility Statement'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Accessibility Statement') }}</h1>
    <p class="mt-2 text-sm text-navy-500">{{ __('Last updated:') }} {{ \App\Services\Settings::get('legal_updated.accessibility', now()->format('F j, Y')) }}</p>
    <div class="prose-navy mt-8 space-y-5 text-lg leading-relaxed text-navy-700">

        <p>{{ __(":brand is committed to making this website usable by everyone, including people using screen readers, keyboard navigation, and assistive technology.", ["brand" => \App\Services\Settings::brand("name")]) }}</p>
        <p>{{ __("Our accessibility practices include: large readable text, strong color contrast, descriptive headings and labels, keyboard-accessible navigation, a skip-to-content link, and forms with clear error messages.") }}</p>
        <p>{{ __("We aim to meet WCAG 2.1 Level AA. If you have difficulty using any part of this site — or would prefer information by phone, mail, or large print — please contact us and we will gladly accommodate you.") }}</p>
    </div>
</div>
@endsection
