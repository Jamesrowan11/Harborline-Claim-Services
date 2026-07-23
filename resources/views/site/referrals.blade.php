@extends('layouts.site')
@section('title', __('Professional & Attorney Referrals'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Professional & Attorney Referrals') }}</h1>
    <div class="mt-8 space-y-6 text-lg leading-relaxed text-navy-700">
        <p>{{ __('Attorneys, estate administrators, financial professionals, and community organizations sometimes encounter clients who may have unclaimed surplus proceeds from a past property sale.') }}</p>
        <p>{{ __('We welcome professional relationships built on the same rules we apply to everyone else: honest language about "possible" funds, verification before outreach, written agreements, and legal work handled by licensed counsel.') }}</p>
        <p>{{ __('If you are an attorney interested in receiving referrals for surplus-fund claim work — or a professional who would like us to research a matter for a client — please contact us. Referral and fee arrangements, where permitted, are documented in writing and reviewed by counsel.') }}</p>
        {{-- [ATTORNEY REVIEW REQUIRED] Referral-fee language must be reviewed for compliance with state bar and consumer-protection rules before publication. --}}
    </div>
    <a href="{{ route('site.contact') }}" class="btn-primary mt-8">{{ __('Contact Us') }}</a>
</div>
@endsection
