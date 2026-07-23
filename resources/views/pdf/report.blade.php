<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reportName }}</title>
    <style>
        @page { margin: 90px 40px 70px 40px; }
        header { position: fixed; top: -60px; left: 0; right: 0; font-family: Georgia, serif; }
        footer { position: fixed; bottom: -50px; left: 0; right: 0; font-size: 9px; color: #666; border-top: 1px solid #ccc; padding-top: 6px; }
        .page-number:after { content: counter(page); }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1b2a4a; }
        h1 { font-family: Georgia, serif; font-size: 16px; margin: 0; }
        .meta { font-size: 9px; color: #555; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead { display: table-header-group; }
        th { background: #1b2a4a; color: #fff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e3e6ee; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .num { text-align: right; }
        .totals td { font-weight: bold; border-top: 2px solid #1b2a4a; }
    </style>
</head>
<body>
<header>
    <h1>{{ $reportName }} — {{ \App\Services\Settings::brand('name') }}</h1>
    <p class="meta">
        {{ __('Generated') }}: {{ now()->format('Y-m-d H:i') }} • {{ __('Prepared by') }}: {{ $preparedBy }}
        • {{ __('Filters') }}: {{ collect($filters)->filter()->map(fn ($v, $k) => "$k: $v")->implode(', ') ?: __('none') }}
    </p>
</header>
<footer>
    {{ str_replace('{name}', $preparedBy, config('branding.confidentiality_footer')) }}
    &nbsp;&nbsp;|&nbsp;&nbsp; {{ __('Page') }} <span class="page-number"></span>
</footer>
<main>
    <table>
        <thead><tr>@foreach ($columns as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @php $totals = []; @endphp
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        @php
                            $isCurrency = ($columns[$i]['format'] ?? null) === 'currency';
                            if ($isCurrency && is_numeric($cell)) { $totals[$i] = ($totals[$i] ?? 0) + (float) $cell; }
                        @endphp
                        <td class="{{ $isCurrency ? 'num' : '' }}">{{ $isCurrency && $cell !== null ? '$'.number_format((float) $cell, 2) : $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
            @if ($totals)
                <tr class="totals">
                    @foreach ($columns as $i => $column)
                        <td class="{{ isset($totals[$i]) ? 'num' : '' }}">{{ isset($totals[$i]) ? '$'.number_format($totals[$i], 2) : ($i === 0 ? __('Totals') : '') }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
    @if (empty($rows))<p>{{ __('No rows match the selected filters.') }}</p>@endif
</main>
</body>
</html>
