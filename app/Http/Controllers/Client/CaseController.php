<?php

namespace App\Http\Controllers\Client;

use App\Events\AgreementSigned;
use App\Events\ClientMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\AuditEvent;
use App\Models\CaseFile;
use App\Models\Document;
use App\Models\PortalMessage;
use App\Services\DocumentStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Client portal case screens. Every method authorizes via CaseFilePolicy,
 * which restricts clients to cases their claimant record is attached to.
 * Only client-safe data is passed to views: simplified status labels,
 * client-visible tasks/documents, and the client's own messages. Staff notes,
 * skip-tracing data, fraud scoring, and other claimants are never exposed.
 */
class CaseController extends Controller
{
    public function show(Request $request, CaseFile $case)
    {
        $this->authorize('view', $case);

        $case->load([
            'stage',
            'documentRequests' => fn ($q) => $q->whereIn('status', ['requested', 'received']),
            'documents' => fn ($q) => $q->where('client_visible', true)->where('status', 'approved'),
            'tasks' => fn ($q) => $q->where('client_visible', true)->where('status', '!=', 'done'),
            'agreements' => fn ($q) => $q->whereIn('status', ['sent', 'signed']),
            'portalMessages' => fn ($q) => $q->with('sender:id,name,user_type')->latest()->limit(50),
            'deadlines' => fn ($q) => $q->whereNull('met_at')->where('source', 'appointment'),
        ]);

        $claim = $case->claims()->latest()->first();
        $payments = $case->stage?->client_label === 'Completed' || $case->stage?->client_label === 'Payment Being Processed'
            ? $case->payments()->where('payment_type', 'client_distribution')->get()
            : collect();

        return view('client.case', [
            'case' => $case,
            'claimSubmitted' => $claim?->filed_at !== null,
            'payments' => $payments,
        ]);
    }

    public function uploadDocument(Request $request, CaseFile $case, DocumentStorageService $storage)
    {
        $this->authorize('view', $case);

        $request->validate([
            'file' => ['required', 'file', 'max:'.(config('security.max_upload_mb') * 1024),
                'mimetypes:'.implode(',', config('security.allowed_upload_mimes'))],
            'title' => ['required', 'string', 'max:160'],
            'document_request_id' => ['nullable', 'integer'],
        ]);

        $document = $storage->store($request->file('file'), $case, [
            'title' => (string) $request->string('title'),
            'category' => 'general',
            'claimant_id' => $request->user()->claimant_id,
        ]);

        if ($request->filled('document_request_id')) {
            $case->documentRequests()
                ->whereKey($request->integer('document_request_id'))
                ->where('status', 'requested')
                ->update(['status' => 'received', 'fulfilled_document_id' => $document->id]);
        }

        return back()->with('status', __('Thank you — your document was uploaded securely and is pending review.'));
    }

    public function downloadDocument(Request $request, CaseFile $case, Document $document)
    {
        $this->authorize('view', $case);
        abort_unless($document->case_id === $case->id, 404);
        $this->authorize('download', $document);

        AuditEvent::record('document_downloaded', $document);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    public function sendMessage(Request $request, CaseFile $case)
    {
        $this->authorize('view', $case);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        PortalMessage::query()->create([
            'case_id' => $case->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $case->caseManager?->notify(new \App\Notifications\InternalAlertNotification(
            __('New client message on :number', ['number' => $case->case_number]), $case->case_number));

        event(new ClientMessageReceived($case));

        return back()->with('status', __('Your message has been sent to your case manager.'));
    }

    public function showAgreement(Request $request, CaseFile $case, Agreement $agreement)
    {
        $this->authorize('view', $case);
        abort_unless($agreement->case_id === $case->id, 404);
        abort_unless(in_array($agreement->status, ['sent', 'signed'], true), 404);

        AuditEvent::record('agreement_viewed', $agreement);

        return view('client.agreement', compact('case', 'agreement'));
    }

    public function signAgreement(Request $request, CaseFile $case, Agreement $agreement)
    {
        $this->authorize('view', $case);
        abort_unless($agreement->case_id === $case->id, 404);
        abort_unless($agreement->status === 'sent', 422, __('This agreement is not available for signature.'));

        $data = $request->validate([
            'signature_name' => ['required', 'string', 'max:160'],
            'agree' => ['accepted'],
        ]);

        $agreement->update([
            'status' => 'signed',
            'signed_at' => now(),
            'signature_name' => $data['signature_name'],
            'signature_ip' => $request->ip(),
            'signature_consent_text' => __('Signed electronically by typing my name, with consent to electronic records and signatures.'),
        ]);

        event(new AgreementSigned($case, null, ['agreement_id' => $agreement->id]));

        return back()->with('status', __('Agreement signed. A copy is available in your documents.'));
    }
}
