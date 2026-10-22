<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\TransferController as CpTransferController;
use App\Models\Fund;
use App\Models\FundTransfer;
use App\Models\PaymentMethod;
use App\Services\Finance\FundTransferService;
use App\Services\Finance\ReversalService;
use App\Support\DateRange;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends CpTransferController
{
    use RespondsWithJson;

    protected const RELATIONS = ['fund', 'fromMethod', 'toMethod', 'currency', 'toCurrency'];

    public function index(Request $request): JsonResponse
    {
        return response()->json(TableSort::paginate(
            FundTransfer::query()
                ->with(self::RELATIONS)
                ->when($request->fund_id, fn ($q, $id) => $q->where('fund_id', $id))
                ->when($request->currency_id, fn ($q, $id) => $q->where(function ($qq) use ($id) {
                    $qq->where('currency_id', $id)->orWhere('to_currency_id', $id);
                }))
                ->when($request->q, fn ($q, $term) => $q->where('notes', 'like', "%{$term}%"))
                ->when($request->boolean('active_only', true), fn ($q) => $q->active()),
            [
                'fund' => ['query' => fn () => Fund::query()->select('name')->whereColumn('funds.id', 'fund_transfers.fund_id')],
                'from' => ['query' => fn () => PaymentMethod::query()->select('name')->whereColumn('payment_methods.id', 'fund_transfers.from_payment_method_id')],
                'to' => ['query' => fn () => PaymentMethod::query()->select('name')->whereColumn('payment_methods.id', 'fund_transfers.to_payment_method_id')],
                'amount' => ['column' => 'amount', 'dir' => 'desc'],
                'rate' => ['column' => 'exchange_rate', 'dir' => 'desc'],
                'date' => ['column' => 'transfer_date', 'dir' => 'desc'],
            ],
            'date'
        ));
    }

    public function store(Request $request, FundTransferService $service): JsonResponse
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
            'transfer_date' => DateRange::inputRules(),
            'notes' => ['nullable', 'string'],
        ]);

        $data['to_currency_id'] = $data['to_currency_id'] ?? $data['currency_id'];
        $isFx = (int) $data['currency_id'] !== (int) $data['to_currency_id'];

        if ($isFx && empty($data['to_amount']) && empty($data['exchange_rate'])) {
            return response()->json(['message' => 'عند اختلاف العملة أدخل المبلغ المستلم أو سعر التحويل يدوياً.'], 422);
        }

        if ($isFx && empty($data['to_amount']) && ! empty($data['exchange_rate'])) {
            // rate = وحدات المصدر لكل 1 من الوجهة (شيكل→دولار: 365÷3.65=100)
            $data['to_amount'] = round((float) $data['amount'] / (float) $data['exchange_rate'], 2);
        }

        if ($isFx && ! empty($data['to_amount']) && empty($data['exchange_rate'])) {
            $data['exchange_rate'] = round((float) $data['amount'] / (float) $data['to_amount'], 8);
        }

        $transfer = $this->attempt(fn () => $service->transfer($data));

        return $this->created($transfer->load(self::RELATIONS), 'تم تنفيذ التحويل.');
    }

    public function show(FundTransfer $transfer): JsonResponse
    {
        return $this->ok($transfer->load(self::RELATIONS));
    }

    public function reverse(FundTransfer $transfer, ReversalService $reversals): JsonResponse
    {
        $this->attempt(fn () => $reversals->reverseTransfer($transfer));

        return $this->ok($transfer->fresh(self::RELATIONS), 'تم إلغاء التحويل.');
    }
}
