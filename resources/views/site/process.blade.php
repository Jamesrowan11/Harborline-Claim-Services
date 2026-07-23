@extends('layouts.site')
@section('title', __('Our Process'))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="font-serif text-4xl font-bold">{{ __('Our Process') }}</h1>
    <p class="mt-4 text-lg text-navy-700">{{ __('From first contact to final decision, here is what working with us looks like.') }}</p>
    <ol class="mt-10 space-y-8">
        @foreach ([
            [__('Free preliminary research'), __('We review public records for the property and sale you ask about. You pay nothing for this step, and we will tell you plainly if we find nothing.')],
            [__('Verification with the funds holder'), __('If records suggest possible remaining funds, we work to confirm directly with the court, county, trustee, or other holder whether money actually remains and whether it appears claimable.')],
            [__('A clear conversation with you'), __('We explain what we found, what we could not confirm, what the process would involve, and what any fee arrangement would be — in writing, before you commit to anything.')],
            [__('Written agreement (attorney-reviewed)'), __('If you choose to proceed, everything is put in a written agreement. You are encouraged to have your own attorney review it. You can decline at any point.')],
            [__('Document collection'), __('We help you gather exactly what the funds holder requires — through our secure portal, never by unprotected email.')],
            [__('Professional review and filing'), __('Steps that require legal work are handled by independent licensed attorneys. The claim is submitted to the official funds holder.')],
            [__('The official decision'), __('The court, county, or trustee decides the claim — not us. If approved, funds are distributed under the applicable rules, and we walk you through every figure.')],
        ] as $i => [$title, $text])
            <li class="flex gap-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-navy-800 font-serif text-lg font-bold text-gold-300">{{ $i + 1 }}</span>
                <div>
                    <h2 class="font-serif text-xl font-bold">{{ $title }}</h2>
                    <p class="mt-2 leading-relaxed text-navy-700">{{ $text }}</p>
                </div>
            </li>
        @endforeach
    </ol>
    <div class="mt-10"><x-independence-disclaimer /></div>
</div>
@endsection
