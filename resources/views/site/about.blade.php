@extends('layouts.site')
@section('title', __('About Us'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('About :name', ['name' => \App\Services\Settings::brand('name')]) }}</h1>
    <div class="mt-8 space-y-6 text-lg leading-relaxed text-navy-700">
        <p>{{ __(':name is a small, independently owned research and assistance company based in Maryland. We focus on one thing: helping people understand and navigate the administrative process for possible surplus funds left over after certain property sales.', ['name' => \App\Services\Settings::brand('name')]) }}</p>
        @if (\App\Services\Settings::brand('parent_company'))
            <p>{{ __('We operate as part of :parent.', ['parent' => \App\Services\Settings::brand('parent_company')]) }}</p>
        @endif
        <p>{{ __('We built this company around a simple belief: people deserve straight answers about money that may belong to them — without pressure, without exaggerated promises, and without confusing legal language.') }}</p>
        <h2 class="font-serif text-2xl font-bold text-navy-900">{{ __('What guides us') }}</h2>
        <ul class="list-disc space-y-3 pl-6">
            <li>{{ __('Honesty first: "possible" means possible, and "verified" means we confirmed it with the official holder.') }}</li>
            <li>{{ __('No pressure: you can say no, pause, or walk away at any point before an agreement — and often after.') }}</li>
            <li>{{ __('Security: sensitive documents move only through our encrypted portal.') }}</li>
            <li>{{ __('Respect for the law: legal work belongs with licensed attorneys, and official decisions belong with the funds holder.') }}</li>
        </ul>
    </div>
    <div class="mt-10"><x-independence-disclaimer /></div>
</div>
@endsection
