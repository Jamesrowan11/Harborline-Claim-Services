@extends('layouts.portal')
@section('title', __('Administration'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Administration — Settings') }}</h1>
<nav class="no-print mt-3 flex flex-wrap gap-2 text-sm">
    <a class="btn-outline !px-3 !py-1.5" href="{{ route('portal.admin.users') }}">{{ __('Users & Roles') }}</a>
    <a class="btn-outline !px-3 !py-1.5" href="{{ route('portal.admin.stages') }}">{{ __('Pipeline Stages') }}</a>
    <a class="btn-outline !px-3 !py-1.5" href="{{ route('portal.admin.templates') }}">{{ __('Templates') }}</a>
    <a class="btn-outline !px-3 !py-1.5" href="{{ route('portal.admin.retention') }}">{{ __('Retention') }}</a>
</nav>
<form method="post" action="{{ route('portal.admin.settings.update') }}" class="mt-6 max-w-3xl space-y-6">
    @csrf
    <div class="card space-y-4">
        <h2 class="font-serif text-lg font-bold">{{ __('Branding') }}</h2>
        <p class="text-sm text-navy-500">{{ __('The business name is provisional until entity, trademark, domain, and licensing checks are complete. Renaming here rebrands the entire product — no rebuild needed.') }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['name' => __('Business name'), 'legal_name' => __('Legal name'), 'parent_company' => __('Parent company'), 'tagline' => __('Tagline'), 'case_prefix' => __('Case number prefix'), 'phone' => __('Phone'), 'email' => __('Email'), 'address' => __('Mailing address'), 'primary_state' => __('Primary state')] as $key => $label)
                <div><label class="form-label">{{ $label }}</label><input class="form-input" name="brand[{{ $key }}]" value="{{ $brand[$key] }}"></div>
            @endforeach
        </div>
    </div>
    <div class="card space-y-4">
        <h2 class="font-serif text-lg font-bold">{{ __('Case Numbers & Service Area') }}</h2>
        <div><label class="form-label">{{ __('Case number format') }}</label>
            <input class="form-input font-mono" name="case_number_format" value="{{ $caseNumberFormat }}">
            <p class="mt-1 text-xs text-navy-500">{{ __('Tokens: {PREFIX} {YEAR} {STATE} {COUNTY} {SEQ:6}') }}</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="form-label">{{ __('Counties served (comma separated)') }}</label>
                <input class="form-input" name="counties_served" value="{{ implode(', ', (array) $countiesServed) }}"></div>
            <div><label class="form-label">{{ __('States served') }}</label>
                <input class="form-input" name="states_served" value="{{ implode(', ', (array) $statesServed) }}"></div>
        </div>
    </div>
    <div class="card space-y-4">
        <h2 class="font-serif text-lg font-bold">{{ __('Lead Assignment & Outreach Compliance') }}</h2>
        <div><label class="form-label">{{ __('Assignment rules (JSON: [{"county","state","assignee_email"}])') }}</label>
            <textarea class="form-input font-mono text-sm" name="assignment_rules_json" rows="4">{{ json_encode($assignmentRules, JSON_PRETTY_PRINT) }}</textarea></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="form-label">{{ __('Minimum verification level for outreach (0-3)') }}</label>
                <input class="form-input" type="number" min="0" max="3" name="outreach_min_verification_level" value="{{ $outreachMinVerification }}"></div>
            <div><label class="form-label">{{ __('Max outbound contacts per recipient per week') }}</label>
                <input class="form-input" type="number" min="1" max="20" name="outreach_max_contacts_per_week" value="{{ $outreachMaxPerWeek }}"></div>
        </div>
    </div>
    <button class="btn-primary">{{ __('Save Settings') }}</button>
</form>
@endsection
