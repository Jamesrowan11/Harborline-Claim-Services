@extends('layouts.portal')
@section('title', __('Task Center'))
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-serif text-2xl font-bold">{{ __('Task Center') }}</h1>
    @can('tasks.manage')<a href="{{ route('portal.tasks.create') }}" class="btn-primary !py-2">{{ __('New Task') }}</a>@endcan
</div>
<div class="no-print mt-4 flex gap-2 text-sm">
    <a href="{{ route('portal.tasks.index') }}" class="btn-outline !px-3 !py-1.5 {{ !request('filter') ? '!bg-navy-50' : '' }}">{{ __('Open') }}</a>
    <a href="{{ route('portal.tasks.index', ['filter' => 'mine']) }}" class="btn-outline !px-3 !py-1.5 {{ request('filter') === 'mine' ? '!bg-navy-50' : '' }}">{{ __('Mine') }}</a>
    <a href="{{ route('portal.tasks.index', ['filter' => 'overdue']) }}" class="btn-outline !px-3 !py-1.5 {{ request('filter') === 'overdue' ? '!bg-navy-50' : '' }}">{{ __('Overdue') }}</a>
</div>
<div class="card mt-4 overflow-x-auto !p-0">
    <table class="min-w-full divide-y divide-navy-100">
        <thead><tr><th class="table-th">{{ __('Task') }}</th><th class="table-th">{{ __('Case / Lead') }}</th><th class="table-th">{{ __('Assigned') }}</th><th class="table-th">{{ __('Priority') }}</th><th class="table-th">{{ __('Due') }}</th><th class="table-th"></th></tr></thead>
        <tbody class="divide-y divide-navy-50">
            @forelse ($tasks as $task)
                <tr class="{{ $task->isOverdue() ? 'bg-burgundy-500/5' : '' }}">
                    <td class="table-td font-medium">{{ $task->title }}</td>
                    <td class="table-td font-mono text-xs">
                        @if ($task->case)<a class="underline" href="{{ route('portal.cases.show', $task->case) }}">{{ $task->case->case_number }}</a>
                        @elseif ($task->lead)<a class="underline" href="{{ route('portal.leads.show', $task->lead) }}">{{ $task->lead->lead_number }}</a>@endif
                    </td>
                    <td class="table-td">{{ $task->assignee?->name }}</td>
                    <td class="table-td"><span class="badge {{ in_array($task->priority, ['high', 'urgent']) ? 'bg-burgundy-500/15 text-burgundy-700' : 'bg-navy-100' }}">{{ $task->priority }}</span></td>
                    <td class="table-td {{ $task->isOverdue() ? 'font-semibold text-burgundy-600' : '' }}">{{ $task->due_at?->format('M j, Y') ?? '—' }}</td>
                    <td class="table-td">@can('tasks.manage')<form method="post" action="{{ route('portal.tasks.complete', $task) }}">@csrf<button class="underline">{{ __('Done') }}</button></form>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-td py-8 text-center text-navy-400">{{ __('No tasks.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $tasks->links() }}</div>
@endsection
