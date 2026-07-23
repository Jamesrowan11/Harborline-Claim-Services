@extends('layouts.client')
@section('title', __('My Cases'))
@section('content')
<h1 class="font-serif text-3xl font-bold">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
<p class="mt-2 text-navy-600">{{ __('Here is the current status of your case with us. If anything is unclear, message your case manager — we are happy to explain.') }}</p>
<div class="mt-8 space-y-4">
    @forelse ($cases as $case)
        <a href="{{ route('client.cases.show', $case) }}" class="card block transition hover:shadow-md">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-mono text-sm text-navy-500">{{ $case->case_number }}</p>
                    <p class="mt-1 font-serif text-xl font-bold">{{ $case->clientStatusLabel() }}</p>
                </div>
                @if ($case->documentRequests->isNotEmpty())
                    <span class="badge bg-gold-100 text-gold-800">{{ trans_choice(':count document needed|:count documents needed', $case->documentRequests->count(), ['count' => $case->documentRequests->count()]) }}</span>
                @endif
            </div>
        </a>
    @empty
        <div class="card text-center text-navy-500">{{ __('No cases are linked to your account yet. If you believe this is an error, please contact us.') }}</div>
    @endforelse
</div>
@endsection
