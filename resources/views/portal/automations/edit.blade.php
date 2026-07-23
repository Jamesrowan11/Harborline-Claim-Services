@extends('layouts.portal')
@section('title', $automation->exists ? $automation->name : __('New Automation'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ $automation->exists ? __('Edit Automation') : __('New Automation') }}</h1>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <form method="post" action="{{ $automation->exists ? route('portal.automations.update', $automation) : route('portal.automations.store') }}" class="card space-y-4">
        @csrf @if ($automation->exists) @method('PUT') @endif
        <div><label class="form-label">{{ __('Name') }} *</label><input class="form-input" name="name" required value="{{ old('name', $automation->name) }}"></div>
        <div><label class="form-label">{{ __('Description') }}</label><textarea class="form-input" name="description" rows="2">{{ old('description', $automation->description) }}</textarea></div>
        <div><label class="form-label">{{ __('Trigger') }} *</label>
            <select class="form-input" name="trigger_event">
                @foreach ($triggers as $trigger)<option value="{{ $trigger }}" @selected(old('trigger_event', $automation->trigger_event) === $trigger)>{{ $trigger }}</option>@endforeach
            </select></div>
        <div><label class="form-label">{{ __('Conditions (JSON array of {field, operator, value})') }}</label>
            <textarea class="form-input font-mono text-sm" name="conditions_json" rows="4">{{ old('conditions_json', json_encode($automation->conditions ?? [], JSON_PRETTY_PRINT)) }}</textarea>
            @error('conditions_json')<p class="form-error">{{ $message }}</p>@enderror</div>
        <div><label class="form-label">{{ __('Actions (JSON array of {type, params})') }} *</label>
            <textarea class="form-input font-mono text-sm" name="actions_json" rows="6">{{ old('actions_json', json_encode($automation->actions ?? [], JSON_PRETTY_PRINT)) }}</textarea>
            @error('actions_json')<p class="form-error">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="form-label">{{ __('Max runs / hour') }}</label><input class="form-input" type="number" name="max_runs_per_hour" value="{{ old('max_runs_per_hour', $automation->max_runs_per_hour ?? 100) }}"></div>
            <div><label class="form-label">{{ __('Max runs / case / day') }}</label><input class="form-input" type="number" name="max_runs_per_case_per_day" value="{{ old('max_runs_per_case_per_day', $automation->max_runs_per_case_per_day ?? 5) }}"></div>
        </div>
        <button class="btn-primary">{{ __('Save (returns to draft if sensitive)') }}</button>
    </form>
    <div class="space-y-6">
        @if ($automation->exists)
        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Mode & Safety') }}</h2>
            <p class="mt-1 text-sm text-navy-500">{{ __('Draft: never runs. Test: logs matches without acting. Approval: queues runs for manual approval. Active: runs live.') }}</p>
            @can('automations.approve')
            <form method="post" action="{{ route('portal.automations.mode', $automation) }}" class="mt-3 flex items-end gap-3">
                @csrf
                <div><label class="form-label text-xs">{{ __('Mode') }}</label>
                    <select class="form-input !py-1.5" name="mode">
                        @foreach (['draft', 'test', 'approval', 'active'] as $mode)<option value="{{ $mode }}" @selected($automation->mode === $mode)>{{ $mode }}</option>@endforeach
                    </select></div>
                <label class="flex items-center gap-2 pb-2 text-sm"><input type="hidden" name="paused" value="0"><input type="checkbox" name="paused" value="1" @checked($automation->paused)> {{ __('Paused') }}</label>
                <button class="btn-outline !py-2">{{ __('Apply') }}</button>
            </form>
            @endcan
            @if ($automation->is_sensitive)
                <p class="mt-3 rounded bg-gold-50 px-3 py-2 text-sm">{{ __('This automation sends outbound communications and requires administrator approval to activate. Edits reset approval.') }}</p>
            @endif
        </div>
        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Recent Runs') }}</h2>
            <ul class="mt-3 max-h-72 space-y-1 overflow-y-auto text-xs">
                @forelse ($automation->runs as $run)
                    <li>
                        <span class="badge {{ $run->status === 'failed' ? 'bg-burgundy-500/15 text-burgundy-700' : 'bg-navy-100' }}">{{ $run->status }}</span>
                        {{ $run->created_at->format('M j H:i') }} — {{ Str::limit($run->message, 90) }}
                    </li>
                @empty<li class="text-navy-400">{{ __('No runs yet.') }}</li>@endforelse
            </ul>
        </div>
        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Version History') }}</h2>
            <ul class="mt-3 space-y-1 text-xs">
                @foreach ($automation->versions as $version)
                    <li>v{{ $version->version }} — {{ $version->created_at->format('M j, Y H:i') }}</li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
