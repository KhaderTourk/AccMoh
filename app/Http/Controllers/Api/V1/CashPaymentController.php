<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentDirection;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\CashPaymentController as CpCashPaymentController;
use App\Models\CashPayment;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Services\Finance\CashPaymentService;
use App\Support\DateRange;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashPaymentController extends CpCashPaymentController
{
    use RespondsWithJson;

    protected const RELATIONS = ['party', 'fund', 'currency', 'fxCurrency', 'paymentMethod'];

    public function incoming(Request $request): JsonResponse
    {
        return $this->listing($request, PaymentDirection::Incoming);
    }

    public function outgoing(Request $request): JsonResponse
    {
        return $this->listing($request, PaymentDirection::Outgoing);
    }

    public function store(Request $request, string $direction, CashPaymentService $service): JsonResponse
    {
        $data = $this->validated($request, PaymentDirection::from($direction));
        $payment = $this->attempt(fn () => $service->record($data));

        return $this->created($payment->load(self::RELATIONS), 'تم تسجيل الدفعة.');
    }

    public function show(CashPayment $payment): JsonResponse
    {
        return $this->ok($payment->load(self::RELATIONS));
    }

    public function update(Request $request, CashPayment $payment, CashPaymentService $service): JsonResponse
    {
        abort_if($payment->is_reversed, 404);

        $data = $this->validated($request, $payment->direction, $payment);
        $updated = $this->attempt(fn () => $service->update($payment, $data));

        return $this->ok($updated->load(self::RELATIONS), 'تم تعديل الدفعة.');
    }

    protected function listing(Request $request, PaymentDirection $direction): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        return response()->json(TableSort::paginate(
            CashPayment::query()
                ->with(self::RELATIONS)
                ->where('direction', $direction)
                ->active()
                ->withoutArchivedParties()
                ->when($request->fund_id, fn ($q, $id) => $q->where('fund_id', $id))
                ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
                ->when($request->payment_method_id, fn ($q, $id) => $q->where('payment_method_id', $id))
                ->when($request->party_type, fn ($q, $type) => $q->where('party_type', $type))
                ->when($request->party_id, fn ($q, $id) => $q->where('party_id', $id))
                ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to))
                ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                        ->orWhere('account_holder_name', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                })),
            [
                'occurred_on' => ['column' => 'occurred_on', 'dir' => 'desc'],
                'name' => ['column' => 'name'],
                'fund' => ['query' => fn () => Fund::query()->select('name')->whereColumn('funds.id', 'cash_payments.fund_id')],
                'method' => ['query' => fn () => PaymentMethod::query()->select('name')->whereColumn('payment_methods.id', 'cash_payments.payment_method_id')],
                'amount' => ['column' => 'amount', 'dir' => 'desc'],
            ],
            'occurred_on'
        ));
    }

    protected function validated(Request $request, PaymentDirection $direction, ?CashPayment $payment = null): array
    {
        // API clients send party_type + party_id directly instead of the CP's party_key.
        if (! $request->filled('party_key') && $request->filled('party_type') && $request->filled('party_id')) {
            $request->merge(['party_key' => $request->party_type.':'.$request->party_id]);
        }

        return parent::validated($request, $direction, $payment);
    }
}
