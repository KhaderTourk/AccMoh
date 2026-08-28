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
            ->with(['fund', 'fromMethod', 'toMethod', 'currency', 'toCurrency'])
            ->when($request->fund_id, fn ($q, $id) => $q->where('fund_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where(function ($qq) use ($id) {
                $qq->where('currency_id', $id)->orWhere('to_currency_id', $id);
            }))
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
            'from_payment_method_id' => ['required', 'exists:payment_methods,id'],
            'to_payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'to_currency_id' => ['nullable', 'exists:currencies,id'],
            'to_amount' => ['nullable', 'numeric', 'gt:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'fee_amount' => ['nullable', 'numeric', 'gte:0'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['to_currency_id'] = $data['to_currency_id'] ?? $data['currency_id'];
        $isFx = (int) $data['currency_id'] !== (int) $data['to_currency_id'];

        if ($isFx && empty($data['to_amount']) && empty($data['exchange_rate'])) {
            return back()->withInput()->with('error', 'عند اختلاف العملة أدخل المبلغ المستلم أو سعر التحويل يدوياً.');
        }

        if ($isFx && empty($data['to_amount']) && ! empty($data['exchange_rate'])) {
            // rate = وحدات المصدر لكل 1 من الوجهة (شيكل→دولار: 365÷3.65=100)
            $data['to_amount'] = round((float) $data['amount'] / (float) $data['exchange_rate'], 2);
        }

        if ($isFx && ! empty($data['to_amount']) && empty($data['exchange_rate'])) {
            $data['exchange_rate'] = round((float) $data['amount'] / (float) $data['to_amount'], 8);
        }

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
