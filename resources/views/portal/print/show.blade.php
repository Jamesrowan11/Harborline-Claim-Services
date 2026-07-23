@extends('layouts.portal')
@section('title', $reportName)
@section('content')
<div class="no-print flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-serif text-2xl font-bold">{{ $reportName }}</h1>
    <div class="flex gap-2">
        <button onclick="window.print()" class="btn-primary !py-2">{{ __('Print') }}</button>
        <a class="btn-outline !py-2" href="{{ route('portal.print.export', array_merge(['report' => $reportKey, 'format' => 'xlsx'], $filters)) }}">XLSX</a>
        <a class="btn-outline !py-2" href="{{ route('portal.print.export', array_merge(['report' => $reportKey, 'format' => 'csv'], $filters)) }}">CSV</a>
        <a class="btn-outline !py-2" href="{{ route('portal.print.export', array_merge(['report' => $reportKey, 'format' => 'pdf'], $filters)) }}">PDF</a>
    </div>
</div>
<div class="mt-4 bg-white p-6 shadow-sm print:p-0 print:shadow-none">
    <div class="mb-4 border-b border-navy-200 pb-3">
        <h2 class="font-serif text-xl font-bold">{{ $reportName }} — {{ \App\Services\Settings::brand('name') }}</h2>
        <p class="text-xs text-navy-500">
            {{ __('Generated') }}: {{ now()->format('Y-m-d H:i') }} • {{ __('Prepared by') }}: {{ auth()->user()->name }}
            • {{ __('Filters') }}: {{ collect($filters)->filter()->map(fn ($v, $k) => "$k: $v")->implode(', ') ?: __('none') }}
        </p>
    </div>
    <table class="min-w-full text-sm">
        <thead><tr>@foreach ($columns as $column)<th class="table-th border-b-2 border-navy-800">{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-navy-100">
            @forelse ($rows as $row)
                <tr>@foreach ($row as $i => $cell)
                    <td class="table-td {{ ($columns[$i]['format'] ?? null) === 'currency' ? 'text-right' : '' }}">
                        {{ ($columns[$i]['format'] ?? null) === 'currency' && $cell !== null ? '$'.number_format((float) $cell, 2) : $cell }}
                    </td>
                @endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="table-td py-6 text-center text-navy-400">{{ __('No rows match the selected filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="mt-6 border-t border-navy-200 pt-3 text-xs text-navy-400">{{ str_replace('{name}', auth()->user()->name, config('branding.confidentiality_footer')) }}</p>
</div>
@endsection
