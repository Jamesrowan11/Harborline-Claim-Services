@extends('layouts.portal')
@section('title', __('Print & Export Center'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Print & Export Center') }}</h1>
<p class="mt-2 max-w-2xl text-sm text-navy-500">{{ __('Every report supports print preview, print-friendly mode, CSV, formatted XLSX (bold frozen headers, filters, totals, confidentiality footer), and PDF in Letter or Legal, portrait or landscape.') }}</p>
@foreach (collect($reports)->groupBy('group') as $group => $groupReports)
    <h2 class="mt-8 font-serif text-lg font-bold text-navy-700">{{ $group }}</h2>
    <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($groupReports as $key => $report)
            @php $key = collect($reports)->search($report); @endphp
            <div class="card">
                <h3 class="font-semibold">{{ $report['name'] }}</h3>
                <form method="get" action="{{ route('portal.print.show', $key) }}" class="mt-3 space-y-2 text-sm">
                    <div class="flex gap-2">
                        <input class="form-input !py-1" type="date" name="from" aria-label="{{ __('From') }}">
                        <input class="form-input !py-1" type="date" name="to" aria-label="{{ __('To') }}">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button class="btn-outline !px-3 !py-1">{{ __('Preview / Print') }}</button>
                        <button class="btn-outline !px-3 !py-1" formaction="{{ route('portal.print.export', $key) }}" name="format" value="xlsx">XLSX</button>
                        <button class="btn-outline !px-3 !py-1" formaction="{{ route('portal.print.export', $key) }}" name="format" value="csv">CSV</button>
                        <button class="btn-outline !px-3 !py-1" formaction="{{ route('portal.print.export', $key) }}" name="format" value="pdf">PDF</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@endforeach
@endsection
