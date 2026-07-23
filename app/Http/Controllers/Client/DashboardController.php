<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $claimantId = $request->user()->claimant_id;

        $cases = $claimantId
            ? CaseFile::query()
                ->whereHas('claimants', fn ($q) => $q->whereKey($claimantId))
                ->with(['stage', 'documentRequests' => fn ($q) => $q->where('status', 'requested')])
                ->orderByDesc('created_at')->get()
            : collect();

        return view('client.dashboard', compact('cases'));
    }
}
