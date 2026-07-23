@extends('layouts.portal')
@section('title', __('New Lead'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('New Lead (staff entry)') }}</h1>
<form method="post" action="{{ route('portal.leads.store') }}" class="card mt-6 max-w-2xl space-y-4">
    @csrf
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="form-label">{{ __('First name') }} *</label><input class="form-input" name="first_name" required value="{{ old('first_name') }}"></div>
        <div><label class="form-label">{{ __('Middle') }}</label><input class="form-input" name="middle_name" value="{{ old('middle_name') }}"></div>
        <div><label class="form-label">{{ __('Last name') }} *</label><input class="form-input" name="last_name" required value="{{ old('last_name') }}"></div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="form-label">{{ __('Former owner') }}</label><input class="form-input" name="former_owner_name" value="{{ old('former_owner_name') }}"></div>
        <div><label class="form-label">{{ __('Relationship') }}</label><input class="form-input" name="relationship_to_owner" value="{{ old('relationship_to_owner') }}"></div>
    </div>
    <div><label class="form-label">{{ __('Property address') }}</label><input class="form-input" name="property_address" value="{{ old('property_address') }}"></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="form-label">{{ __('County') }}</label><input class="form-input" name="property_county" value="{{ old('property_county') }}"></div>
        <div><label class="form-label">{{ __('State') }}</label><input class="form-input" name="property_state" maxlength="2" value="{{ old('property_state', 'MD') }}"></div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="form-label">{{ __('Email') }}</label><input class="form-input" type="email" name="email" value="{{ old('email') }}"></div>
        <div><label class="form-label">{{ __('Phone') }}</label><input class="form-input" name="phone" value="{{ old('phone') }}"></div>
    </div>
    <div><label class="form-label">{{ __('Notes') }}</label><textarea class="form-input" name="description" rows="3">{{ old('description') }}</textarea></div>
    <label class="flex items-center gap-2"><input type="checkbox" name="consent_to_contact" value="1"> {{ __('Caller gave verbal consent to contact') }}</label>
    <button class="btn-primary">{{ __('Create Lead') }}</button>
</form>
@endsection
