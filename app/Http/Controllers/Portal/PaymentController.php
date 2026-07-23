<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CaseFile;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::query()
            ->with(['case', 'recorder'])
            ->when($request->filled('type'), fn ($q) => $q->where('payment_type', $request->string('type')))
            ->when($request->filled('from'), fn ($q) => $q->where('occurred_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('occurred_on', '<=', $request->date('to')))
            ->orderByDesc('occurred_on')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $totals = Payment::query()
            ->selectRaw('payment_type, sum(amount) as total')
            ->groupBy('payment_type')->pluck('total', 'payment_type');

        return view('portal.payments.index', compact('payments', 'totals'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'case_id' => ['required', 'exists:cases,id'],
            'payment_type' => ['required', 'in:funds_received,client_distribution,company_fee,refund'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'in:check,ach,wire,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'occurred_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['recorded_by'] = auth()->id();
        $payment = Payment::query()->create($data);

        event(new \App\Events\PaymentEntered(CaseFile::query()->find($data['case_id']), null, ['payment_id' => $payment->id, 'payment_type' => $payment->payment_type]));

        return back()->with('status', __('Payment recorded.'));
    }
}
