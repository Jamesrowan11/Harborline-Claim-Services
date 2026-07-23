<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CaseTask;
use App\Models\User;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = CaseTask::query()
            ->with(['case', 'lead', 'assignee'])
            ->when($request->string('filter')->value() === 'mine', fn ($q) => $q->where('assigned_to', auth()->id()))
            ->when($request->string('filter')->value() === 'overdue', fn ($q) => $q->where('status', '!=', 'done')->where('due_at', '<', now()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $q->where('status', '!=', 'done'))
            ->orderByRaw('due_at is null, due_at asc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return view('portal.tasks.index', compact('tasks'));
    }

    public function create(Request $request)
    {
        return view('portal.tasks.create', [
            'staff' => User::query()->where('user_type', 'staff')->whereNull('deactivated_at')->orderBy('name')->get(),
            'caseId' => $request->integer('case_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'case_id' => ['nullable', 'exists:cases,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date'],
        ]);

        $data['created_by'] = auth()->id();
        CaseTask::query()->create($data);

        return redirect()->route('portal.tasks.index')->with('status', __('Task created.'));
    }

    public function complete(CaseTask $task)
    {
        $task->update(['status' => 'done', 'completed_at' => now()]);
        $task->case?->touchActivity();

        return back()->with('status', __('Task completed.'));
    }
}
