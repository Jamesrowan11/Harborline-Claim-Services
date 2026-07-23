@extends('layouts.client')
@section('title', $agreement->title)
@section('content')
<a href="{{ route('client.cases.show', $case) }}" class="text-sm text-navy-500 underline">← {{ __('Back to case') }}</a>
<h1 class="mt-2 font-serif text-3xl font-bold">{{ $agreement->title }}</h1>
<div class="card mt-6">
    <pre class="whitespace-pre-wrap font-sans text-base leading-relaxed">{{ $agreement->body_rendered }}</pre>
</div>
@if ($agreement->status === 'sent')
    <div class="card mt-6 border-l-4 border-l-gold-500">
        <h2 class="font-serif text-xl font-bold">{{ __('Electronic Signature') }}</h2>
        <p class="mt-2 text-sm text-navy-600">{{ __('Take your time. You are welcome to have your own attorney review this agreement before signing. Typing your full legal name below and clicking Sign acts as your electronic signature.') }}</p>
        <form method="post" action="{{ route('client.cases.agreements.sign', [$case, $agreement]) }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label class="form-label" for="signature_name">{{ __('Type your full legal name') }}</label>
                <input class="form-input max-w-md" id="signature_name" name="signature_name" required>
                @error('signature_name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="agree" value="1" required class="mt-1.5">
                <span class="text-sm">{{ __('I have read this agreement, I agree to its terms, and I consent to signing it electronically.') }}</span>
            </label>
            @error('agree')<p class="form-error">{{ $message }}</p>@enderror
            <button class="btn-primary">{{ __('Sign Agreement') }}</button>
        </form>
    </div>
@else
    <p class="mt-6 rounded-md bg-gold-50 px-4 py-3">{{ __('Signed by :name on :date.', ['name' => $agreement->signature_name, 'date' => $agreement->signed_at?->format('F j, Y g:ia')]) }}</p>
@endif
@endsection
