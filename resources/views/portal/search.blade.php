@extends('layouts.portal')
@section('title', __('Search'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Search results for ":q"', ['q' => $q]) }}</h1>
<ul class="mt-6 space-y-2">
    @forelse ($results as $result)
        <li class="card !py-3">
            <span class="badge bg-navy-100 text-navy-700">{{ $result['type'] }}</span>
            <a href="{{ $result['url'] }}" class="ml-2 font-semibold underline">{{ $result['label'] }}</a>
        </li>
    @empty
        <li class="text-navy-500">{{ __('No matches found.') }}</li>
    @endforelse
</ul>
@endsection
