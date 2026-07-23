@extends('layouts.site')
@section('title', __('Check for Possible Funds'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Check for Possible Funds') }}</h1>
    <p class="mt-4 text-lg leading-relaxed text-navy-700">
        {{ __('Tell us what you know — even partial information helps. We will research public records at no cost and let you know what we find. Submitting this form does not obligate you to anything.') }}
    </p>
    <div class="mt-6"><x-independence-disclaimer /></div>
    <p class="mt-4 rounded-md bg-navy-50 px-4 py-3 text-sm text-navy-700">
        {{ __('For your protection, this form never asks for Social Security numbers, bank details, or payment information — and we never will by email or phone at this stage.') }}
    </p>

    <form method="post" action="{{ route('site.check.store') }}" class="card mt-8 space-y-6">
        @csrf
        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">

        <fieldset>
            <legend class="font-serif text-xl font-bold">{{ __('About you') }}</legend>
            <div class="mt-4 grid gap-5 sm:grid-cols-3">
                <div><label class="form-label" for="first_name">{{ __('First name') }} *</label>
                    <input class="form-input" id="first_name" name="first_name" required value="{{ old('first_name') }}">
                    @error('first_name')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div><label class="form-label" for="middle_name">{{ __('Middle name') }}</label>
                    <input class="form-input" id="middle_name" name="middle_name" value="{{ old('middle_name') }}"></div>
                <div><label class="form-label" for="last_name">{{ __('Last name') }} *</label>
                    <input class="form-input" id="last_name" name="last_name" required value="{{ old('last_name') }}">
                    @error('last_name')<p class="form-error">{{ $message }}</p>@enderror</div>
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><label class="form-label" for="former_owner_name">{{ __('Former property owner (if not you)') }}</label>
                    <input class="form-input" id="former_owner_name" name="former_owner_name" value="{{ old('former_owner_name') }}"></div>
                <div><label class="form-label" for="relationship_to_owner">{{ __('Your relationship to the former owner') }} *</label>
                    <select class="form-input" id="relationship_to_owner" name="relationship_to_owner" required>
                        @foreach ([__('I am the former owner'), __('Spouse'), __('Child'), __('Parent'), __('Sibling'), __('Other relative'), __('Estate representative'), __('Other')] as $option)
                            <option value="{{ $option }}" @selected(old('relationship_to_owner') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('relationship_to_owner')<p class="form-error">{{ $message }}</p>@enderror</div>
            </div>
            <div class="mt-5">
                <span class="form-label">{{ __('Is the former owner living?') }}</span>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2"><input type="radio" name="former_owner_living" value="1" @checked(old('former_owner_living') === '1')> {{ __('Yes') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" name="former_owner_living" value="0" @checked(old('former_owner_living') === '0')> {{ __('No') }}</label>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="font-serif text-xl font-bold">{{ __('About the property') }}</legend>
            <div class="mt-4 space-y-5">
                <div><label class="form-label" for="property_address">{{ __('Property address') }} *</label>
                    <input class="form-input" id="property_address" name="property_address" required value="{{ old('property_address') }}">
                    @error('property_address')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div><label class="form-label" for="property_county">{{ __('County') }} *</label>
                        <input class="form-input" id="property_county" name="property_county" required value="{{ old('property_county') }}">
                        @error('property_county')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label" for="property_state">{{ __('State') }} *</label>
                        <input class="form-input" id="property_state" name="property_state" maxlength="2" required placeholder="MD" value="{{ old('property_state', 'MD') }}">
                        @error('property_state')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label" for="approximate_sale_date">{{ __('Approximate sale date') }}</label>
                        <input class="form-input" id="approximate_sale_date" name="approximate_sale_date" placeholder="{{ __('e.g. spring 2023') }}" value="{{ old('approximate_sale_date') }}"></div>
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div><label class="form-label" for="court_case_number">{{ __('Court case number (optional)') }}</label>
                        <input class="form-input" id="court_case_number" name="court_case_number" value="{{ old('court_case_number') }}"></div>
                    <div><label class="form-label" for="parcel_number">{{ __('Parcel number (optional)') }}</label>
                        <input class="form-input" id="parcel_number" name="parcel_number" value="{{ old('parcel_number') }}"></div>
                    <div><label class="form-label" for="tax_account_number">{{ __('Tax account number (optional)') }}</label>
                        <input class="form-input" id="tax_account_number" name="tax_account_number" value="{{ old('tax_account_number') }}"></div>
                </div>
                <div><label class="form-label" for="description">{{ __('Anything else you can tell us') }}</label>
                    <textarea class="form-input" id="description" name="description" rows="4">{{ old('description') }}</textarea></div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="font-serif text-xl font-bold">{{ __('How to reach you') }}</legend>
            <div class="mt-4 grid gap-5 sm:grid-cols-3">
                <div><label class="form-label" for="email">{{ __('Email') }} *</label>
                    <input class="form-input" type="email" id="email" name="email" required value="{{ old('email') }}">
                    @error('email')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div><label class="form-label" for="phone">{{ __('Phone') }}</label>
                    <input class="form-input" id="phone" name="phone" value="{{ old('phone') }}"></div>
                <div><label class="form-label" for="preferred_contact_method">{{ __('Preferred contact method') }} *</label>
                    <select class="form-input" id="preferred_contact_method" name="preferred_contact_method" required>
                        <option value="email" @selected(old('preferred_contact_method') === 'email')>{{ __('Email') }}</option>
                        <option value="phone" @selected(old('preferred_contact_method') === 'phone')>{{ __('Phone') }}</option>
                        <option value="mail" @selected(old('preferred_contact_method') === 'mail')>{{ __('Postal mail') }}</option>
                    </select></div>
            </div>
        </fieldset>

        <fieldset class="space-y-3 rounded-md bg-navy-50 p-5">
            <legend class="sr-only">{{ __('Consents') }}</legend>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="consent_to_contact" value="1" required class="mt-1.5">
                <span>{{ __('I agree that :brand may contact me about this inquiry using the details above.', ['brand' => \App\Services\Settings::brand('name')]) }} *</span>
            </label>
            @error('consent_to_contact')<p class="form-error">{{ $message }}</p>@enderror
            <label class="flex items-start gap-3">
                <input type="checkbox" name="privacy_policy_agreed" value="1" required class="mt-1.5">
                <span>{!! __('I have read and agree to the :link.', ['link' => '<a class="underline" href="'.route('site.privacy').'" target="_blank" rel="noopener">'.__('Privacy Policy').'</a>']) !!} *</span>
            </label>
            @error('privacy_policy_agreed')<p class="form-error">{{ $message }}</p>@enderror
            <label class="flex items-start gap-3">
                <input type="checkbox" name="electronic_consent" value="1" required class="mt-1.5">
                <span>{!! __('I consent to receive communications electronically as described in the :link.', ['link' => '<a class="underline" href="'.route('site.e-consent').'" target="_blank" rel="noopener">'.__('Electronic Communications Consent').'</a>']) !!} *</span>
            </label>
            @error('electronic_consent')<p class="form-error">{{ $message }}</p>@enderror
            <label class="flex items-start gap-3 border-t border-navy-200 pt-3">
                <input type="checkbox" name="sms_consent" value="1" class="mt-1.5" @checked(old('sms_consent'))>
                <span class="text-sm">{!! __('Optional: I agree to receive text messages about my inquiry under the :link. This is not required, and I can opt out at any time by replying STOP.', ['link' => '<a class="underline" href="'.route('site.sms-terms').'" target="_blank" rel="noopener">'.__('SMS Terms').'</a>']) !!}</span>
            </label>
        </fieldset>

        <button class="btn-primary w-full sm:w-auto">{{ __('Submit for Preliminary Review') }}</button>
    </form>
</div>
@endsection
