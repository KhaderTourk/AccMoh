<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\BalanceController as CpBalanceController;
use App\Services\Finance\AdjustmentService;
use App\Services\Finance\BalanceService;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceController extends CpBalanceController
{
    use RespondsWithJson;

    public function index(BalanceService $balances): JsonResponse
    {
        return $this->ok($balances->snapshot());
    }

    public function storeOpening(Request $request, AdjustmentService $adjustments): JsonResponse
    {
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_on' => DateRange::inputRules(),
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry = $this->attempt(fn () => $adjustments->opening($data));

        return $this->created($entry, 'تم تسجيل الرصيد الافتتاحي.');
    }
}
