<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CaseTask;
use App\Models\Deadline;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __invoke(Request $request)
    {
        $month = $request->date('month') ?? now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $tasks = CaseTask::query()->with('case')->whereBetween('due_at', [$start, $end])->get()
            ->groupBy(fn ($t) => $t->due_at->format('Y-m-d'));
        $deadlines = Deadline::query()->with('case')->whereBetween('due_at', [$start, $end])->get()
            ->groupBy(fn ($d) => $d->due_at->format('Y-m-d'));

        return view('portal.calendar', compact('tasks', 'deadlines', 'start', 'end'));
    }
}
