<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationVersion;
use App\Services\Settings;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public const TRIGGERS = [
        'lead.created', 'case.created', 'case.field_changed', 'case.stage_changed',
        'document.uploaded', 'document.approved', 'document.rejected', 'agreement.signed',
        'claim.filed', 'payment.entered', 'deadline.approaching', 'task.overdue',
        'case.inactive', 'source.stale', 'client.message_received', 'email.bounced',
        'sms.opt_out', 'mail.returned', 'attorney.review_requested', 'verification.expired',
        'schedule.tick',
    ];

    public const SENSITIVE_ACTIONS = ['send_email', 'send_sms', 'generate_letter', 'send_portal_notification'];

    public function index(Request $request)
    {
        $automations = Automation::query()
            ->withCount(['runs as failed_runs_count' => fn ($q) => $q->where('status', 'failed')])
            ->withCount('runs')
            ->orderBy('name')
            ->paginate(50);

        return view('portal.automations.index', [
            'automations' => $automations,
            'globalStop' => (bool) Settings::get('automation_global_stop', false),
        ]);
    }

    public function create()
    {
        return view('portal.automations.edit', ['automation' => new Automation(['mode' => 'draft', 'conditions' => [], 'actions' => []]), 'triggers' => self::TRIGGERS]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        $data['is_sensitive'] = $this->isSensitive($data['actions']);

        $automation = Automation::query()->create($data);
        $this->snapshot($automation);

        return redirect()->route('portal.automations.edit', $automation)->with('status', __('Automation created in draft mode.'));
    }

    public function edit(Automation $automation)
    {
        $automation->load(['runs' => fn ($q) => $q->latest()->limit(25), 'versions' => fn ($q) => $q->latest('version')->limit(10)]);

        return view('portal.automations.edit', ['automation' => $automation, 'triggers' => self::TRIGGERS]);
    }

    public function update(Request $request, Automation $automation)
    {
        $data = $this->validated($request);
        $data['is_sensitive'] = $this->isSensitive($data['actions']);
        $data['version'] = $automation->version + 1;

        // Any edit to a sensitive automation clears its approval.
        if ($data['is_sensitive']) {
            $data['approved_by'] = null;
            $data['approved_at'] = null;
            if ($automation->mode === 'active') {
                $data['mode'] = 'draft';
            }
        }

        $automation->update($data);
        $this->snapshot($automation);

        return back()->with('status', __('Automation updated (version :v).', ['v' => $automation->version]));
    }

    public function changeMode(Request $request, Automation $automation)
    {
        $data = $request->validate(['mode' => ['required', 'in:draft,test,approval,active'], 'paused' => ['nullable', 'boolean']]);

        if (array_key_exists('paused', $data)) {
            $automation->update(['paused' => (bool) $data['paused']]);
        }

        if (in_array($data['mode'], ['active', 'approval'], true) && $automation->is_sensitive && $automation->approved_at === null) {
            $automation->update(['approved_by' => auth()->id(), 'approved_at' => now(), 'mode' => $data['mode']]);
        } else {
            $automation->update(['mode' => $data['mode']]);
        }

        return back()->with('status', __('Automation mode set to :mode.', ['mode' => $data['mode']]));
    }

    public function emergencyStop(Request $request)
    {
        $stop = $request->boolean('stop', true);
        Settings::set('automation_global_stop', $stop, 'automations');

        return back()->with('status', $stop
            ? __('EMERGENCY STOP enabled — no automations will run until re-enabled.')
            : __('Emergency stop lifted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trigger_event' => ['required', 'in:'.implode(',', self::TRIGGERS)],
            'conditions_json' => ['nullable', 'json'],
            'actions_json' => ['required', 'json'],
            'max_runs_per_hour' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_runs_per_case_per_day' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $conditions = json_decode($data['conditions_json'] ?? '[]', true) ?: [];
        $actions = json_decode($data['actions_json'], true) ?: [];

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger_event' => $data['trigger_event'],
            'conditions' => $conditions,
            'actions' => $actions,
            'max_runs_per_hour' => $data['max_runs_per_hour'] ?? 100,
            'max_runs_per_case_per_day' => $data['max_runs_per_case_per_day'] ?? 5,
        ];
    }

    private function isSensitive(array $actions): bool
    {
        return collect($actions)->pluck('type')->intersect(self::SENSITIVE_ACTIONS)->isNotEmpty();
    }

    private function snapshot(Automation $automation): void
    {
        AutomationVersion::query()->create([
            'automation_id' => $automation->id,
            'version' => $automation->version,
            'definition' => [
                'name' => $automation->name,
                'trigger_event' => $automation->trigger_event,
                'conditions' => $automation->conditions,
                'actions' => $automation->actions,
            ],
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
