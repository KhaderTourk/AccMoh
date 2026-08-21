<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\Currency;
use App\Services\Finance\AdjustmentService;
use App\Services\Finance\BalanceService;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    use LoadsFinanceLookups;

    public function index(BalanceService $balances)
    {
        return view('cp.finance.balances.index', [
            'snapshot' => $balances->snapshot(),
        ] + $this->financeLookups());
    }

    public function storeOpening(Request $request, AdjustmentService $adjustments)
    {
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_on' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $adjustments->opening($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.balances.index')->with('success', 'تم تسجيل الرصيد الافتتاحي.');
    }
}
