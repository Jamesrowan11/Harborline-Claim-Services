<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\Communication;
use App\Models\DocumentTemplate;
use App\Services\OutreachGate;
use App\Services\TemplateRenderer;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function index(Request $request)
    {
        $communications = Communication::query()
            ->with(['case', 'template', 'generator'])
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('case_id'), fn ($q) => $q->where('case_id', $request->integer('case_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return view('portal.communications.index', compact('communications'));
    }

    public function compose(Request $request, TemplateRenderer $renderer)
    {
        $case = $request->filled('case_id') ? CaseFile::query()->find($request->integer('case_id')) : null;
        $templates = DocumentTemplate::query()->where('active', true)->whereIn('category', ['letter', 'email', 'sms'])->orderBy('name')->get();

        $preview = null;
        if ($request->filled('template_id') && $case) {
            $template = $templates->firstWhere('id', $request->integer('template_id'));
            if ($template) {
                $preview = $renderer->render($template, $case, $case->claimants()->wherePivot('role', 'primary')->first());
            }
        }

        return view('portal.communications.compose', compact('case', 'templates', 'preview'));
    }

    public function store(Request $request, TemplateRenderer $renderer)
    {
        $data = $request->validate([
            'case_id' => ['required', 'exists:cases,id'],
            'template_id' => ['required', 'exists:document_templates,id'],
            'channel' => ['required', 'in:email,sms,letter'],
        ]);

        $case = CaseFile::query()->findOrFail($data['case_id']);
        $template = DocumentTemplate::query()->findOrFail($data['template_id']);
        $claimant = $case->claimants()->wherePivot('role', 'primary')->first();
        $rendered = $renderer->render($template, $case, $claimant);

        $recipient = match ($data['channel']) {
            'email' => $claimant?->emailAddresses()->where('opted_out', false)->value('email'),
            'sms' => $claimant?->phoneNumbers()->where('sms_capable', true)->value('number'),
            default => null,
        };

        Communication::query()->create([
            'case_id' => $case->id,
            'claimant_id' => $claimant?->id,
            'channel' => $data['channel'],
            'direction' => 'outbound',
            'recipient' => $recipient,
            'subject' => $rendered['subject'],
            'body_rendered' => $rendered['body'],
            'document_template_id' => $template->id,
            'template_version' => $template->version,
            'status' => 'draft',
            'generated_by' => auth()->id(),
        ]);

        return redirect()->route('portal.communications.index', ['case_id' => $case->id])
            ->with('status', __('Draft created. It must be approved before sending.'));
    }

    public function approve(Communication $communication)
    {
        abort_unless($communication->status === 'draft', 422);
        $communication->update(['approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('status', __('Communication approved. It can now be sent.'));
    }

    public function send(Communication $communication, OutreachGate $gate)
    {
        abort_unless($communication->approved_at !== null, 422, 'Communication must be approved before sending.');
        abort_unless(in_array($communication->status, ['draft', 'failed'], true), 422);

        if (in_array($communication->channel, ['email', 'sms'], true)) {
            $check = $gate->check(
                $communication->channel,
                $communication->template,
                $communication->case,
                $communication->lead,
                $communication->claimant,
            );
            if ($check !== true) {
                return back()->withErrors(['send' => __('Blocked by outreach rules: :reason', ['reason' => $check])]);
            }
        }

        $communication->update(['status' => 'queued']);
        \App\Jobs\SendCommunicationJob::dispatch($communication->id);

        return back()->with('status', __('Communication queued for delivery.'));
    }
}
