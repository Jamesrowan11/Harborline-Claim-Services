@extends('layouts.portal')
@section('title', __('New Case'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('New Case') }}</h1>
<form method="post" action="{{ route('portal.cases.store') }}" class="card mt-6 max-w-2xl space-y-4">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="form-label">{{ __('County') }} *</label><input class="form-input" name="county" required value="{{ old('county') }}"></div>
        <div><label class="form-label">{{ __('State') }} *</label><input class="form-input" name="state" maxlength="2" required value="{{ old('state', 'MD') }}"></div>
    </div>
    <div><label class="form-label">{{ __('Property address') }}</label><input class="form-input" name="address_line1" value="{{ old('address_line1') }}"></div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="form-label">{{ __('City') }}</label><input class="form-input" name="city" value="{{ old('city') }}"></div>
        <div><label class="form-label">{{ __('ZIP') }}</label><input class="form-input" name="zip" value="{{ old('zip') }}"></div>
        <div><label class="form-label">{{ __('Parcel #') }}</label><input class="form-input" name="parcel_number" value="{{ old('parcel_number') }}"></div>
    </div>
    <div><label class="form-label">{{ __('Estimated surplus (if known)') }}</label><input class="form-input" type="number" step="0.01" min="0" name="estimated_surplus" value="{{ old('estimated_surplus') }}"></div>
    <button class="btn-primary">{{ __('Create Case') }}</button>
</form>
@endsection
