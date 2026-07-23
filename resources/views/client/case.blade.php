@extends('layouts.client')
@section('title', $case->case_number)
@section('content')
<a href="{{ route('client.dashboard') }}" class="text-sm text-navy-500 underline">← {{ __('My Cases') }}</a>
<h1 class="mt-2 font-serif text-3xl font-bold">{{ $case->case_number }}</h1>
<p class="mt-2 text-xl text-navy-700">{{ __('Status:') }} <span class="font-serif font-bold text-navy-900">{{ $case->clientStatusLabel() }}</span></p>
@if ($claimSubmitted)
    <p class="mt-1 text-sm text-gold-700">✓ {{ __('A claim has been submitted to the funds holder on your behalf.') }}</p>
@endif

<div class="mt-8 space-y-6">
    @if ($case->documentRequests->isNotEmpty())
        <section class="card border-l-4 border-l-gold-500">
            <h2 class="font-serif text-xl font-bold">{{ __('Documents We Need From You') }}</h2>
            <ul class="mt-3 space-y-4">
                @foreach ($case->documentRequests as $docRequest)
                    <li class="rounded-md bg-navy-50 p-4">
                        <p class="font-semibold">{{ $docRequest->name }} <span class="badge ml-1 bg-white">{{ $docRequest->status }}</span></p>
                        @if ($docRequest->instructions)<p class="mt-1 text-sm text-navy-600">{{ $docRequest->instructions }}</p>@endif
                        @if ($docRequest->status === 'requested')
                            <form method="post" action="{{ route('client.cases.documents.store', $case) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3">
                                @csrf
                                <input type="hidden" name="document_request_id" value="{{ $docRequest->id }}">
                                <input type="hidden" name="title" value="{{ $docRequest->name }}">
                                <input type="file" name="file" required class="text-sm">
                                <button class="btn-primary !py-2">{{ __('Upload Securely') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            @error('file')<p class="form-error mt-2">{{ $message }}</p>@enderror
        </section>
    @endif

    @if ($case->tasks->isNotEmpty())
        <section class="card">
            <h2 class="font-serif text-xl font-bold">{{ __('Outstanding Items') }}</h2>
            <ul class="mt-3 list-disc space-y-1 pl-6 text-navy-700">
                @foreach ($case->tasks as $task)<li>{{ $task->title }}</li>@endforeach
            </ul>
        </section>
    @endif

    @if ($case->agreements->isNotEmpty())
        <section class="card">
            <h2 class="font-serif text-xl font-bold">{{ __('Agreements') }}</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($case->agreements as $agreement)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <span>{{ $agreement->title }} <span class="badge bg-navy-100">{{ $agreement->status === 'sent' ? __('awaiting your signature') : __('signed') }}</span></span>
                        <a class="btn-outline !px-4 !py-1.5 text-sm" href="{{ route('client.cases.agreements.show', [$case, $agreement]) }}">{{ $agreement->status === 'sent' ? __('Review & Sign') : __('View') }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($payments->isNotEmpty())
        <section class="card">
            <h2 class="font-serif text-xl font-bold">{{ __('Payments to You') }}</h2>
            <ul class="mt-3 space-y-1 text-navy-700">
                @foreach ($payments as $payment)
                    <li>${{ number_format((float) $payment->amount, 2) }} — {{ $payment->occurred_on->format('F j, Y') }} ({{ $payment->method ?? __('method on file') }})</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($case->documents->isNotEmpty())
        <section class="card">
            <h2 class="font-serif text-xl font-bold">{{ __('Your Documents') }}</h2>
            <ul class="mt-3 space-y-1">
                @foreach ($case->documents as $document)
                    <li><a class="underline" href="{{ route('client.cases.documents.download', [$case, $document]) }}">{{ $document->title }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <h2 class="font-serif text-xl font-bold">{{ __('Messages') }}</h2>
        <ul class="mt-3 max-h-80 space-y-2 overflow-y-auto">
            @forelse ($case->portalMessages->sortBy('created_at') as $message)
                <li class="rounded-md p-3 {{ $message->sender?->id === auth()->id() ? 'bg-gold-50 text-right' : 'bg-navy-50' }}">
                    <p class="text-xs font-semibold text-navy-500">{{ $message->sender?->id === auth()->id() ? __('You') : $message->sender?->name }}</p>
                    <p class="mt-1">{{ $message->body }}</p>
                    <p class="mt-1 text-xs text-navy-400">{{ $message->created_at->format('M j, Y g:ia') }}</p>
                </li>
            @empty
                <li class="text-navy-400">{{ __('No messages yet. Questions are always welcome.') }}</li>
            @endforelse
        </ul>
        <form method="post" action="{{ route('client.cases.messages.store', $case) }}" class="mt-4 flex gap-3">
            @csrf
            <label class="sr-only" for="body">{{ __('Message') }}</label>
            <textarea id="body" name="body" rows="2" required class="form-input flex-1" placeholder="{{ __('Write a message to your case manager…') }}"></textarea>
            <button class="btn-primary self-end">{{ __('Send') }}</button>
        </form>
    </section>
</div>
@endsection
