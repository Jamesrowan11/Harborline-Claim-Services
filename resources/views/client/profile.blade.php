@extends('layouts.client')
@section('title', __('My Details'))
@section('content')
<h1 class="font-serif text-3xl font-bold">{{ __('My Details & Preferences') }}</h1>
<div class="mt-8 space-y-6">
    <section class="card">
        <h2 class="font-serif text-xl font-bold">{{ __('Contact Details') }}</h2>
        <form method="post" action="{{ route('client.profile.update') }}" class="mt-4 space-y-4">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="form-label">{{ __('Email') }}</label>
                    <input class="form-input" type="email" name="email" value="{{ $claimant?->emailAddresses->first()?->email }}"></div>
                <div><label class="form-label">{{ __('Phone') }}</label>
                    <input class="form-input" name="phone" value="{{ $claimant?->phoneNumbers->first()?->number }}"></div>
            </div>
            <div><label class="form-label">{{ __('Mailing address') }}</label>
                <input class="form-input" name="address_line1" value="{{ $claimant?->addresses->first()?->line1 }}"></div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label class="form-label">{{ __('City') }}</label><input class="form-input" name="city" value="{{ $claimant?->addresses->first()?->city }}"></div>
                <div><label class="form-label">{{ __('State') }}</label><input class="form-input" maxlength="2" name="state" value="{{ $claimant?->addresses->first()?->state }}"></div>
                <div><label class="form-label">{{ __('ZIP') }}</label><input class="form-input" name="zip" value="{{ $claimant?->addresses->first()?->zip }}"></div>
            </div>
            <button class="btn-primary">{{ __('Save Changes') }}</button>
        </form>
    </section>
    <section class="card">
        <h2 class="font-serif text-xl font-bold">{{ __('Communication Preferences') }}</h2>
        <div class="mt-4 space-y-4 text-sm">
            <form method="post" action="{{ route('client.profile.withdraw-sms') }}">
                @csrf
                <button class="btn-outline !py-2">{{ __('Stop Text Messages (withdraw SMS consent)') }}</button>
            </form>
            <form method="post" action="{{ route('client.profile.stop-contact') }}" onsubmit="return confirm('{{ __('Are you sure? We will stop contacting you except where legally required.') }}')">
                @csrf
                <button class="btn-outline !py-2">{{ __('Ask Us to Stop All Contact') }}</button>
            </form>
            <form method="post" action="{{ route('client.profile.close-account') }}" onsubmit="return confirm('{{ __('Request account closure? A team member will confirm what is possible under record-keeping rules.') }}')">
                @csrf
                <button class="btn-outline !py-2 !border-burgundy-600 !text-burgundy-600">{{ __('Request Account Closure') }}</button>
            </form>
        </div>
    </section>
</div>
@endsection
