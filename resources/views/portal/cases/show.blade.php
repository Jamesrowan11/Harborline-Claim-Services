@extends('layouts.portal')
@section('title', $case->case_number)
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-serif text-2xl font-bold">{{ $case->case_number }}</h1>
        <p class="text-sm text-navy-500">
            <span class="badge bg-navy-100 text-navy-700">{{ $case->stage?->name }}</span>
            <span class="ml-2">{{ __('Client sees:') }} <em>{{ $case->clientStatusLabel() }}</em></span>
            @if ($case->isOnHold())<span class="badge ml-2 bg-burgundy-500/15 text-burgundy-700">{{ __('ON HOLD') }}</span>@endif
            @if ($case->stage?->requires_attorney_review)<span class="badge ml-2 bg-gold-100 text-gold-800">{{ __('Attorney review required') }}</span>@endif
        </p>
    </div>
    @can('cases.change_stage')
    <form method="post" action="{{ route('portal.cases.stage', $case) }}" class="flex items-end gap-2">
        @csrf
        <div>
            <label class="form-label !mb-0 text-xs">{{ __('Move to stage') }}</label>
            <select name="stage_id" class="form-input !py-1.5">
                @foreach ($stages as $stage)
                    <option value="{{ $stage->id }}" @selected($case->pipeline_stage_id === $stage->id)>{{ $stage->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-primary !py-2">{{ __('Move') }}</button>
    </form>
    @endcan
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Overview') }}</h2>
            <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                <div><dt class="font-semibold text-navy-500">{{ __('County') }}</dt><dd>{{ $case->county }}, {{ $case->state }}</dd></div>
                <div><dt class="font-semibold text-navy-500">{{ __('Property') }}</dt><dd>{{ $case->property?->address_line1 ?? '—' }}</dd></div>
                <div><dt class="font-semibold text-navy-500">{{ __('Parcel #') }}</dt><dd>{{ $case->property?->parcel_number ?? '—' }}</dd></div>
                <div><dt class="font-semibold text-navy-500">{{ __('Estimated surplus') }}</dt><dd>{{ $case->estimated_surplus ? '$'.number_format((float) $case->estimated_surplus, 2) : '—' }}</dd></div>
                <div><dt class="font-semibold text-navy-500">{{ __('Verified surplus') }}</dt><dd>{{ $case->verified_surplus ? '$'.number_format((float) $case->verified_surplus, 2) : '—' }}</dd></div>
                <div><dt class="font-semibold text-navy-500">{{ __('Verification level') }}</dt><dd>{{ $case->verification_level }}</dd></div>
                <div><dt class="font-semibold text-navy-500">{{ __('Funds holder') }}</dt><dd>{{ $case->fundsHolder?->name ?? '—' }}</dd></div>
                <div><dt class="font-semibold text-navy-500">{{ __('Legal complexity') }}</dt><dd>{{ $case->legal_complexity }}</dd></div>
                <div><dt class="font-semibold text-navy-500">{{ __('Last activity') }}</dt><dd>{{ $case->last_activity_at?->diffForHumans() ?? '—' }}</dd></div>
            </dl>
            @can('cases.update')
            <details class="mt-4">
                <summary class="cursor-pointer text-sm font-semibold text-navy-600">{{ __('Edit case fields') }}</summary>
                <form method="post" action="{{ route('portal.cases.update', $case) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <div><label class="form-label text-xs">{{ __('Assigned to') }}</label>
                        <select class="form-input !py-1.5" name="assigned_to">
                            <option value="">—</option>
                            @foreach ($staff as $member)<option value="{{ $member->id }}" @selected($case->assigned_to === $member->id)>{{ $member->name }}</option>@endforeach
                        </select></div>
                    <div><label class="form-label text-xs">{{ __('Case manager') }}</label>
                        <select class="form-input !py-1.5" name="case_manager_id">
                            <option value="">—</option>
                            @foreach ($staff as $member)<option value="{{ $member->id }}" @selected($case->case_manager_id === $member->id)>{{ $member->name }}</option>@endforeach
                        </select></div>
                    <div><label class="form-label text-xs">{{ __('Estimated surplus') }}</label><input class="form-input !py-1.5" type="number" step="0.01" name="estimated_surplus" value="{{ $case->estimated_surplus }}"></div>
                    <div><label class="form-label text-xs">{{ __('Verified surplus') }}</label><input class="form-input !py-1.5" type="number" step="0.01" name="verified_surplus" value="{{ $case->verified_surplus }}"></div>
                    <div><label class="form-label text-xs">{{ __('Verification level') }}</label>
                        <select class="form-input !py-1.5" name="verification_level">
                            @foreach (['none', 'preliminary', 'source_confirmed', 'holder_confirmed'] as $level)
                                <option value="{{ $level }}" @selected($case->verification_level === $level)>{{ $level }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="form-label text-xs">{{ __('Legal complexity') }}</label>
                        <select class="form-input !py-1.5" name="legal_complexity">
                            @foreach (['standard', 'probate', 'heirship', 'trust', 'business', 'bankruptcy', 'disputed'] as $lc)
                                <option value="{{ $lc }}" @selected($case->legal_complexity === $lc)>{{ $lc }}</option>
                            @endforeach
                        </select></div>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="compliance_hold" value="0"><input type="checkbox" name="compliance_hold" value="1" @checked($case->compliance_hold)> {{ __('Compliance hold') }}</label>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="automation_paused" value="0"><input type="checkbox" name="automation_paused" value="1" @checked($case->automation_paused)> {{ __('Pause automations') }}</label>
                    @can('compliance.review')
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="outreach_approved" value="0"><input type="checkbox" name="outreach_approved" value="1" @checked($case->outreach_approved)> {{ __('Outreach approved (compliance)') }}</label>
                    @endcan
                    <div class="sm:col-span-2"><button class="btn-primary !py-2">{{ __('Save Changes') }}</button></div>
                </form>
            </details>
            @endcan
        </div>

        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Claimants') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($case->claimants as $claimant)
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $claimant->displayName() }}</span>
                        <span class="badge bg-navy-100 text-navy-700">{{ $claimant->pivot->role }}</span>
                        <span class="text-navy-500">{{ $claimant->emailAddresses->first()?->email }} {{ $claimant->phoneNumbers->first()?->number }}</span>
                        <span class="badge {{ $claimant->identity_verification_status === 'verified' ? 'bg-gold-100 text-gold-800' : 'bg-navy-100 text-navy-500' }}">ID: {{ $claimant->identity_verification_status }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <h2 class="font-serif text-lg font-bold">{{ __('Documents') }}</h2>
                @can('documents.upload')
                <form method="post" action="{{ route('portal.documents.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2 text-sm">
                    @csrf<input type="hidden" name="case_id" value="{{ $case->id }}">
                    <input type="file" name="file" required class="text-xs">
                    <input type="text" name="title" placeholder="{{ __('Title') }}" required class="form-input !w-36 !py-1">
                    <select name="category" class="form-input !w-32 !py-1">
                        @foreach (['identity', 'agreement', 'court', 'property', 'correspondence', 'general'] as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                    </select>
                    <button class="btn-outline !px-3 !py-1">{{ __('Upload') }}</button>
                </form>
                @endcan
            </div>
            <table class="mt-3 min-w-full text-sm">
                <thead><tr><th class="table-th">{{ __('Title') }}</th><th class="table-th">{{ __('Category') }}</th><th class="table-th">{{ __('Status') }}</th><th class="table-th">{{ __('Client?') }}</th><th class="table-th"></th></tr></thead>
                <tbody class="divide-y divide-navy-50">
                    @foreach ($case->documents as $document)
                        <tr>
                            <td class="table-td">{{ $document->title }}</td>
                            <td class="table-td">{{ $document->category }}</td>
                            <td class="table-td"><span class="badge bg-navy-100">{{ $document->status }}</span> <span class="text-xs text-navy-400">{{ $document->virus_scan_status }}</span></td>
                            <td class="table-td">{{ $document->client_visible ? __('visible') : __('staff-only') }}</td>
                            <td class="table-td space-x-2">
                                @can('documents.download')<a class="underline" href="{{ route('portal.documents.download', $document) }}">{{ __('Download') }}</a>@endcan
                                @if ($document->status === 'pending')
                                    @can('documents.approve')
                                        <form class="inline" method="post" action="{{ route('portal.documents.review', $document) }}">@csrf<input type="hidden" name="status" value="approved"><button class="underline">{{ __('Approve') }}</button></form>
                                        <form class="inline" method="post" action="{{ route('portal.documents.review', $document) }}">@csrf<input type="hidden" name="status" value="rejected"><button class="text-burgundy-600 underline">{{ __('Reject') }}</button></form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($case->documentRequests->isNotEmpty())
                <h3 class="mt-4 text-sm font-bold text-navy-600">{{ __('Outstanding document requests') }}</h3>
                <ul class="mt-1 text-sm">
                    @foreach ($case->documentRequests as $docRequest)
                        <li>{{ $docRequest->name }} — <span class="badge bg-navy-100">{{ $docRequest->status }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Recent Communications') }}</h2>
            @can('communications.send')<a class="text-sm underline" href="{{ route('portal.communications.compose', ['case_id' => $case->id]) }}">{{ __('Compose from template') }}</a>@endcan
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($case->communications as $communication)
                    <li class="rounded bg-navy-50 p-2">
                        <span class="badge bg-white">{{ $communication->channel }}</span>
                        <span class="badge bg-white">{{ $communication->direction }}</span>
                        <span class="badge bg-white">{{ $communication->status }}</span>
                        <span class="font-semibold">{{ $communication->subject }}</span>
                        <span class="text-xs text-navy-400">{{ $communication->created_at->format('M j, Y H:i') }}</span>
                    </li>
                @empty
                    <li class="text-navy-400">{{ __('No communications yet.') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Financials') }}</h2>
            <div class="mt-3 grid gap-6 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-bold text-navy-600">{{ __('Claims') }}</h3>
                    <ul class="mt-1 space-y-1 text-sm">
                        @forelse ($case->claims as $claim)
                            <li>{{ $claim->status }} — {{ $claim->amount_claimed ? '$'.number_format((float) $claim->amount_claimed, 2) : '—' }} @if ($claim->filed_at)({{ __('filed') }} {{ $claim->filed_at->format('M j, Y') }})@endif</li>
                        @empty<li class="text-navy-400">{{ __('No claims yet.') }}</li>@endforelse
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-navy-600">{{ __('Payments') }}</h3>
                    <ul class="mt-1 space-y-1 text-sm">
                        @forelse ($case->payments as $payment)
                            <li>{{ str_replace('_', ' ', $payment->payment_type) }}: ${{ number_format((float) $payment->amount, 2) }} ({{ $payment->occurred_on->format('M j, Y') }})</li>
                        @empty<li class="text-navy-400">{{ __('No payments recorded.') }}</li>@endforelse
                    </ul>
                    @can('payments.manage')
                    <form method="post" action="{{ route('portal.payments.store') }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        @csrf<input type="hidden" name="case_id" value="{{ $case->id }}">
                        <select name="payment_type" class="form-input !py-1">
                            @foreach (['funds_received', 'client_distribution', 'company_fee', 'refund'] as $type)<option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>@endforeach
                        </select>
                        <input class="form-input !py-1" type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" required>
                        <input class="form-input !py-1" type="date" name="occurred_on" value="{{ now()->format('Y-m-d') }}" required>
                        <button class="btn-outline !py-1">{{ __('Record') }}</button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Tasks & Deadlines') }}</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($case->tasks->where('status', '!=', 'done') as $task)
                    <li class="flex items-center justify-between gap-2 {{ $task->isOverdue() ? 'text-burgundy-600' : '' }}">
                        <span>{{ $task->title }} @if ($task->due_at)<span class="text-xs text-navy-400">({{ $task->due_at->format('M j') }})</span>@endif</span>
                        @can('tasks.manage')<form method="post" action="{{ route('portal.tasks.complete', $task) }}">@csrf<button class="text-xs underline">{{ __('Done') }}</button></form>@endcan
                    </li>
                @endforeach
            </ul>
            @can('tasks.manage')<a href="{{ route('portal.tasks.create', ['case_id' => $case->id]) }}" class="mt-2 inline-block text-sm underline">{{ __('Add task') }}</a>@endcan
            <h3 class="mt-4 text-sm font-bold text-navy-600">{{ __('Deadlines') }}</h3>
            <ul class="mt-1 space-y-1 text-sm">
                @foreach ($case->deadlines->whereNull('met_at') as $deadline)
                    <li class="{{ $deadline->is_critical ? 'font-semibold text-burgundy-600' : '' }}">{{ $deadline->name }} — {{ $deadline->due_at->format('M j, Y') }}</li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Source Records') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($case->sourceRecords as $source)
                    <li>
                        <span class="font-semibold">{{ $source->title }}</span>
                        <span class="badge {{ $source->isStale() ? 'bg-burgundy-500/15 text-burgundy-700' : 'bg-gold-100 text-gold-800' }}">{{ $source->isStale() ? __('stale') : $source->status }}</span>
                        <span class="block text-xs text-navy-400">{{ $source->source_type }} — {{ $source->retrieved_at?->format('M j, Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Client Messages') }}</h2>
            <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto text-sm">
                @forelse ($case->portalMessages as $message)
                    <li class="rounded p-2 {{ $message->sender?->user_type === 'client' ? 'bg-gold-50' : 'bg-navy-50' }}">
                        <span class="text-xs font-semibold">{{ $message->sender?->name }}</span>
                        <p>{{ $message->body }}</p>
                        <span class="text-xs text-navy-400">{{ $message->created_at->diffForHumans() }}</span>
                    </li>
                @empty<li class="text-navy-400">{{ __('No messages.') }}</li>@endforelse
            </ul>
        </div>

        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Notes (staff only)') }}</h2>
            @can('notes.manage')
            <form method="post" action="{{ route('portal.cases.notes.store', $case) }}" class="mt-2">
                @csrf
                <textarea name="body" rows="2" class="form-input text-sm" required placeholder="{{ __('Add an internal note…') }}"></textarea>
                <button class="btn-outline mt-2 !px-3 !py-1 text-sm">{{ __('Add Note') }}</button>
            </form>
            @endcan
            <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto text-sm">
                @foreach ($case->notes->sortByDesc('created_at') as $note)
                    <li class="rounded bg-navy-50 p-2">{{ $note->body }}<span class="block text-xs text-navy-400">{{ $note->author?->name ?? __('System') }} — {{ $note->created_at->format('M j, Y H:i') }}</span></li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <h2 class="font-serif text-lg font-bold">{{ __('Stage History') }}</h2>
            <ul class="mt-3 space-y-1 text-xs text-navy-500">
                @foreach ($case->stageTransitions->sortByDesc('created_at') as $transition)
                    <li>{{ $transition->created_at->format('M j, Y H:i') }} → <span class="font-semibold text-navy-700">{{ $transition->toStage?->name }}</span> {{ $transition->user ? 'by '.$transition->user->name : '(automation)' }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
