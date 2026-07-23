@extends('layouts.portal')
@section('title', __('New Task'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('New Task') }}</h1>
<form method="post" action="{{ route('portal.tasks.store') }}" class="card mt-6 max-w-xl space-y-4">
    @csrf
    @if ($caseId)<input type="hidden" name="case_id" value="{{ $caseId }}">@endif
    <div><label class="form-label">{{ __('Title') }} *</label><input class="form-input" name="title" required></div>
    <div><label class="form-label">{{ __('Description') }}</label><textarea class="form-input" name="description" rows="3"></textarea></div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="form-label">{{ __('Assign to') }}</label>
            <select class="form-input" name="assigned_to"><option value="">—</option>
                @foreach ($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach
            </select></div>
        <div><label class="form-label">{{ __('Priority') }}</label>
            <select class="form-input" name="priority">
                @foreach (['low', 'normal', 'high', 'urgent'] as $priority)<option value="{{ $priority }}" @selected($priority === 'normal')>{{ $priority }}</option>@endforeach
            </select></div>
        <div><label class="form-label">{{ __('Due') }}</label><input class="form-input" type="date" name="due_at"></div>
    </div>
    <button class="btn-primary">{{ __('Create Task') }}</button>
</form>
@endsection
