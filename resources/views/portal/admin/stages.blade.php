@extends('layouts.portal')
@section('title', __('Pipeline Stages'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Pipeline Stages') }}</h1>
<p class="mt-2 text-sm text-navy-500">{{ __('Internal names and client-facing labels are both editable. The client label is the only thing claimants ever see.') }}</p>
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr><th class="table-th">{{ __('Order') }}</th><th class="table-th">{{ __('Internal name') }}</th><th class="table-th">{{ __('Client label') }}</th><th class="table-th">{{ __('Flags') }}</th><th class="table-th">{{ __('Active') }}</th><th class="table-th"></th></tr></thead>
        <tbody class="divide-y divide-navy-50">
            @foreach ($stages as $stage)
                <tr>
                    <form method="post" action="{{ route('portal.admin.stages.update', $stage) }}">
                        @csrf
                        <td class="table-td w-20"><input class="form-input !w-16 !py-1" type="number" name="sort_order" value="{{ $stage->sort_order }}"></td>
                        <td class="table-td"><input class="form-input !py-1" name="name" value="{{ $stage->name }}"></td>
                        <td class="table-td"><input class="form-input !py-1" name="client_label" value="{{ $stage->client_label }}"></td>
                        <td class="table-td text-xs">
                            {{ $stage->is_closed ? __('closed') : '' }} {{ $stage->is_hold ? __('hold') : '' }}
                            {{ $stage->requires_compliance_review ? __('compliance') : '' }} {{ $stage->requires_attorney_review ? __('attorney') : '' }}
                        </td>
                        <td class="table-td"><input type="checkbox" name="active" value="1" @checked($stage->active)></td>
                        <td class="table-td"><button class="underline">{{ __('Save') }}</button></td>
                    </form>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
