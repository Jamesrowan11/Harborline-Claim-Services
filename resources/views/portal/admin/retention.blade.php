@extends('layouts.portal')
@section('title', __('Retention Policies'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Data Retention Policies') }}</h1>
<p class="mt-2 max-w-2xl text-sm text-navy-500">{{ __('Retention rules run daily via the scheduler. Records under legal hold are never touched, and "delete" actions require the policy to be explicitly activated. [ATTORNEY REVIEW REQUIRED] Retention periods should be set with counsel.') }}</p>
<form method="post" action="{{ route('portal.admin.retention.update') }}" class="card mt-6 max-w-3xl">
    @csrf
    <table class="min-w-full text-sm">
        <thead><tr><th class="table-th">{{ __('Record type') }}</th><th class="table-th">{{ __('Retain (months)') }}</th><th class="table-th">{{ __('Then') }}</th><th class="table-th">{{ __('Active') }}</th></tr></thead>
        <tbody class="divide-y divide-navy-50">
            @foreach ($policies as $policy)
                <tr>
                    <td class="table-td font-medium">{{ str_replace('_', ' ', $policy->record_type) }}</td>
                    <td class="table-td"><input class="form-input !w-28 !py-1" type="number" name="policies[{{ $policy->id }}][retain_months]" value="{{ $policy->retain_months }}" placeholder="{{ __('forever') }}"></td>
                    <td class="table-td">
                        <select class="form-input !py-1" name="policies[{{ $policy->id }}][action_after]">
                            @foreach (['review', 'anonymize', 'delete'] as $action)<option value="{{ $action }}" @selected($policy->action_after === $action)>{{ $action }}</option>@endforeach
                        </select></td>
                    <td class="table-td"><input type="checkbox" name="policies[{{ $policy->id }}][active]" value="1" @checked($policy->active)></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button class="btn-primary mt-4">{{ __('Save Policies') }}</button>
</form>
@endsection
