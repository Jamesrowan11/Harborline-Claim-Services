@extends('layouts.site')
@section('title', __('Document Security Information'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Document Security Information') }}</h1>
    <p class="mt-2 text-sm text-navy-500">{{ __('Last updated:') }} {{ \App\Services\Settings::get('legal_updated.document-security', now()->format('F j, Y')) }}</p>
    <div class="prose-navy mt-8 space-y-5 text-lg leading-relaxed text-navy-700">

        <p>{{ __("Documents you share with us deserve bank-grade care. Here is how we protect them:") }}</p>
        <ul class="list-disc space-y-2 pl-6">
            <li>{{ __("Encrypted in transit: all uploads travel over HTTPS (TLS).") }}</li>
            <li>{{ __("Private storage: files are stored outside the public web root and are never reachable by a public link.") }}</li>
            <li>{{ __("Access controls: only authorized staff working on your file can view your documents, and every view and download is logged.") }}</li>
            <li>{{ __("Malware screening: uploaded files can be scanned before staff open them.") }}</li>
            <li>{{ __("Retention limits: documents are kept only as long as needed under our retention policy and applicable law.") }}</li>
        </ul>
        <p>{{ __("Please never send identification documents or sensitive records by regular email. Use the secure portal or the Upload Requested Documents page.") }}</p>
    </div>
</div>
@endsection
