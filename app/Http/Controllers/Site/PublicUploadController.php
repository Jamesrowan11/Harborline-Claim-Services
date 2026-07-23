<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\Document;
use App\Services\DocumentStorageService;
use Illuminate\Http\Request;

/**
 * Public "Upload Requested Documents" flow for clients without portal logins.
 * Requires reference number + last name + property ZIP before accepting files;
 * everything lands in pending review on the private disk.
 */
class PublicUploadController extends Controller
{
    public function show()
    {
        return view('site.upload');
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'last_name' => ['required', 'string', 'max:80'],
            'zip' => ['required', 'string', 'max:10'],
        ]);

        $case = $this->matchCase($data['reference'], $data['last_name'], $data['zip']);

        if (! $case) {
            return back()->withErrors(['reference' => __('We could not match those details. Please check your letter or call us using the number on our Contact page.')]);
        }

        session(['public_upload_case_id' => $case->id, 'public_upload_expires' => now()->addMinutes(30)->timestamp]);

        return view('site.upload', ['verified' => true, 'reference' => $case->case_number]);
    }

    public function store(Request $request, DocumentStorageService $storage)
    {
        $caseId = session('public_upload_case_id');
        $expires = session('public_upload_expires', 0);
        abort_if(! $caseId || $expires < now()->timestamp, 403);

        $request->validate([
            'file' => ['required', 'file', 'max:'.(config('security.max_upload_mb') * 1024),
                'mimetypes:'.implode(',', config('security.allowed_upload_mimes'))],
            'title' => ['required', 'string', 'max:160'],
        ]);

        $case = CaseFile::query()->findOrFail($caseId);
        $storage->store($request->file('file'), $case, [
            'title' => $request->string('title'),
            'category' => 'general',
        ]);

        return view('site.upload', ['uploaded' => true, 'verified' => true, 'reference' => $case->case_number]);
    }

    private function matchCase(string $reference, string $lastName, string $zip): ?CaseFile
    {
        $case = CaseFile::query()->where('case_number', strtoupper(trim($reference)))->first();
        if (! $case) {
            return null;
        }
        $zip = substr(preg_replace('/[^0-9]/', '', $zip), 0, 5);
        $nameMatch = $case->claimants()->get()->contains(fn ($c) => strcasecmp((string) $c->last_name, $lastName) === 0);
        $zipMatch = $case->property && str_starts_with((string) $case->property->zip, $zip);

        return $nameMatch && ($zipMatch || $case->property === null) ? $case : null;
    }
}
