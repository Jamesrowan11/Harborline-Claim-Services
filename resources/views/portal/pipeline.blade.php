@extends('layouts.portal')
@section('title', __('Pipeline'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Case Pipeline') }}</h1>
<div class="mt-6 flex gap-4 overflow-x-auto pb-4">
    @foreach ($stages->where('cases_count', '>', 0) as $stage)
        <div class="w-72 shrink-0 rounded-lg bg-navy-100/60 p-3">
            <h2 class="flex items-center justify-between px-1 text-sm font-bold text-navy-700">
                {{ $stage->name }} <span class="badge bg-white">{{ $stage->cases_count }}</span>
            </h2>
            <div class="mt-3 space-y-2">
                @foreach ($stage->cases as $case)
                    <a href="{{ route('portal.cases.show', $case) }}" class="block rounded-md bg-white p-3 shadow-sm transition hover:shadow">
                        <p class="font-mono text-sm font-semibold">{{ $case->case_number }}</p>
                        <p class="text-xs text-navy-500">{{ $case->county }}, {{ $case->state }}</p>
                        @if ($case->estimated_surplus)<p class="text-xs text-gold-700">{{ __('Est.') }} ${{ number_format((float) $case->estimated_surplus, 0) }}</p>@endif
                        @if ($case->assignee)<p class="mt-1 text-xs text-navy-400">{{ $case->assignee->name }}</p>@endif
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
