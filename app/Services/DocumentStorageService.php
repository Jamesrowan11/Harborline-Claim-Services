<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\CaseFile;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * All uploads go to the non-public "private" disk, are hashed, and pass
 * through an optional virus-scanning hook before staff review.
 */
class DocumentStorageService
{
    public function store(UploadedFile $file, ?CaseFile $case, array $attributes = []): Document
    {
        $path = $file->store('documents/'.($case?->id ?? 'unassigned'), 'private');

        $document = Document::query()->create(array_merge([
            'case_id' => $case?->id,
            'title' => $attributes['title'] ?? $file->getClientOriginalName(),
            'category' => $attributes['category'] ?? 'general',
            'original_filename' => $file->getClientOriginalName(),
            'disk' => 'private',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'status' => 'pending',
            'virus_scan_status' => config('security.virus_scan.enabled') ? 'pending' : 'skipped',
            'uploaded_by_type' => Auth::user()?->getMorphClass(),
            'uploaded_by_id' => Auth::id(),
        ], array_diff_key($attributes, ['title' => 1, 'category' => 1])));

        if (config('security.virus_scan.enabled')) {
            $this->scan($document);
        }

        event(new \App\Events\DocumentUploaded($case, null, ['document_id' => $document->id]));

        return $document;
    }

    public function scan(Document $document): void
    {
        $fullPath = Storage::disk($document->disk)->path($document->path);
        $command = array_merge(explode(' ', config('security.virus_scan.command')), [$fullPath]);

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        $clean = $process->getExitCode() === 0;
        $document->update(['virus_scan_status' => $clean ? 'clean' : 'infected']);

        if (! $clean) {
            AuditEvent::record('virus_detected', $document);
        }
    }
}
