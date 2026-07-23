<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\CaseFile;
use App\Models\Document;
use App\Services\DocumentStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = Document::query()
            ->with(['case', 'reviewer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('case_id'), fn ($q) => $q->where('case_id', $request->integer('case_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return view('portal.documents.index', compact('documents'));
    }

    public function store(Request $request, DocumentStorageService $storage)
    {
        $request->validate([
            'case_id' => ['required', 'exists:cases,id'],
            'file' => ['required', 'file', 'max:'.(config('security.max_upload_mb') * 1024),
                'mimetypes:'.implode(',', config('security.allowed_upload_mimes'))],
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'in:identity,agreement,court,property,correspondence,general'],
            'client_visible' => ['nullable', 'boolean'],
        ]);

        $case = CaseFile::query()->findOrFail($request->integer('case_id'));

        $storage->store($request->file('file'), $case, [
            'title' => (string) $request->string('title'),
            'category' => (string) $request->string('category'),
            'client_visible' => $request->boolean('client_visible'),
        ]);

        return back()->with('status', __('Document uploaded and pending review.'));
    }

    public function download(Document $document)
    {
        $this->authorize('download', $document);

        AuditEvent::record('document_downloaded', $document);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    public function review(Request $request, Document $document)
    {
        $this->authorize('review', $document);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $document->update(array_merge($data, ['reviewed_by' => auth()->id(), 'reviewed_at' => now()]));

        event($data['status'] === 'approved'
            ? new \App\Events\DocumentApproved($document->case, null, ['document_id' => $document->id])
            : new \App\Events\DocumentRejected($document->case, null, ['document_id' => $document->id]));

        return back()->with('status', __('Document :status.', ['status' => $data['status']]));
    }
}
