<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\FundTransfer;
use App\Services\Finance\FundTransferService;
use App\Services\Finance\ReversalService;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        $transfers = FundTransfer::query()
            ->with(['fund', 'fromMethod', 'toMethod', 'currency'])
            ->when($request->fund_id, fn ($q, $id) => $q->where('fund_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->orderByDesc('transfer_date')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.transfers.index', array_merge(compact('transfers'), $this->financeLookups()));
    }

    public function create()
    {
        return view('cp.finance.transfers.form', $this->financeLookups());
    }

    public function store(Request $request, FundTransferService $service)
    {
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'from_payment_method_id' => ['required', 'exists:payment_methods,id', 'different:to_payment_method_id'],
            'to_payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'fee_amount' => ['nullable', 'numeric', 'gte:0'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $service->transfer($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.transfers.index')->with('success', 'تم تنفيذ التحويل.');
    }

    public function reverse(FundTransfer $transfer, ReversalService $reversals)
    {
        try {
            $reversals->reverseTransfer($transfer);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم إلغاء التحويل.');
    }
}
