@extends('layouts.portal')
@section('title', __('Calendar'))
@section('content')
<div class="flex items-center justify-between">
    <h1 class="font-serif text-2xl font-bold">{{ __('Calendar') }} — {{ $start->format('F Y') }}</h1>
    <div class="flex gap-2">
        <a class="btn-outline !py-1.5 !px-3" href="{{ route('portal.calendar', ['month' => $start->copy()->subMonth()->format('Y-m-d')]) }}">←</a>
        <a class="btn-outline !py-1.5 !px-3" href="{{ route('portal.calendar', ['month' => $start->copy()->addMonth()->format('Y-m-d')]) }}">→</a>
    </div>
</div>
<div class="mt-6 grid grid-cols-7 gap-px overflow-hidden rounded-lg border border-navy-100 bg-navy-100">
    @foreach ([__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')] as $d)
        <div class="bg-navy-50 px-2 py-1 text-center text-xs font-bold text-navy-500">{{ $d }}</div>
    @endforeach
    @php $cursor = $start->copy()->startOfWeek(\Carbon\Carbon::SUNDAY); $endGrid = $end->copy()->endOfWeek(\Carbon\Carbon::SATURDAY); @endphp
    @while ($cursor <= $endGrid)
        @php $key = $cursor->format('Y-m-d'); @endphp
        <div class="min-h-24 bg-white p-1.5 {{ $cursor->month !== $start->month ? 'opacity-40' : '' }}">
            <p class="text-xs font-semibold {{ $cursor->isToday() ? 'text-gold-700' : 'text-navy-400' }}">{{ $cursor->day }}</p>
            @foreach (($deadlines[$key] ?? collect()) as $deadline)
                <a href="{{ $deadline->case ? route('portal.cases.show', $deadline->case) : '#' }}" class="mt-1 block truncate rounded bg-burgundy-500/15 px-1 text-xs text-burgundy-700">{{ $deadline->name }}</a>
            @endforeach
            @foreach (($tasks[$key] ?? collect()) as $task)
                <a href="{{ $task->case ? route('portal.cases.show', $task->case) : route('portal.tasks.index') }}" class="mt-1 block truncate rounded bg-navy-100 px-1 text-xs">{{ $task->title }}</a>
            @endforeach
        </div>
        @php $cursor->addDay(); @endphp
    @endwhile
</div>
@endsection
