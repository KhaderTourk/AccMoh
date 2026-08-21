<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientService;
use App\Models\FinancialAuditLog;
use App\Models\Fund;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ClientPaymentService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
    ) {}

    /**
     * @param  array<int, array{client_service_id:int, amount:mixed}>  $allocations
     */
    public function receive(array $data, array $allocations): ClientPayment
    {
        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('مبلغ الدفعة يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($data, $allocations, $amount) {
            $client = Client::query()->findOrFail($data['client_id']);
            $fund = Fund::business();
            $this->assertAllocations($client, (int) $data['currency_id'], $amount, $allocations);

            $payment = ClientPayment::query()->create([
                'client_id' => $client->id,
                'fund_id' => $fund->id,
                'amount' => $amount,
                'currency_id' => $data['currency_id'],
                'payment_method_id' => $data['payment_method_id'],
                'payer_name' => $data['payer_name'] ?: $client->name,
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
                'ledger_group_id' => 'pending',
                'is_reversed' => false,
            ]);

            foreach ($allocations as $row) {
                $allocated = Money::of($row['amount']);
                if (! Money::isPositive($allocated)) {
                    continue;
                }
                PaymentAllocation::query()->create([
                    'client_payment_id' => $payment->id,
                    'client_service_id' => $row['client_service_id'],
                    'allocated_amount' => $allocated,
                    'currency_id' => $data['currency_id'],
                ]);
            }

            $groupId = $this->ledger->post([[
                'type' => TransactionType::ClientPayment,
                'fund_id' => $fund->id,
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $data['currency_id'],
                'amount' => $amount,
                'occurred_on' => $data['payment_date'],
                'description' => 'دفعة من العميل '.$client->name,
                'notes' => $data['notes'] ?? null,
                'related' => $payment,
            ]]);

            $payment->update(['ledger_group_id' => $groupId]);
            FinancialAuditLog::record('created', $payment, ['amount' => $amount]);

            return $payment->fresh(['allocations', 'client', 'currency', 'paymentMethod']);
        });
    }

    /**
     * @param  array<int, array{client_service_id:int, amount:mixed}>  $allocations
     */
    protected function assertAllocations(Client $client, int $currencyId, string $amount, array $allocations): void
    {
        if ($allocations === []) {
            throw new FinanceException('يجب توزيع الدفعة على خدمة واحدة على الأقل.');
        }

        $seen = [];
        $sum = '0.00';

        foreach ($allocations as $row) {
            $allocated = Money::of($row['amount'] ?? 0);
            if (Money::isZero($allocated)) {
                continue;
            }
            if (! Money::isPositive($allocated)) {
                throw new FinanceException('مبلغ التوزيع يجب أن يكون أكبر من صفر.');
            }

            $serviceId = (int) $row['client_service_id'];
            if (isset($seen[$serviceId])) {
                throw new FinanceException('لا يجوز تكرار الخدمة في توزيع الدفعة.');
            }
            $seen[$serviceId] = true;

            $service = ClientService::query()->billable()->find($serviceId);
            if (! $service) {
                throw new FinanceException('الخدمة المحددة غير موجودة أو ملغاة.');
            }
            if ((int) $service->client_id !== (int) $client->id) {
                throw new FinanceException('لا يجوز تخصيص دفعة لخدمة تخص عميلاً آخر.');
            }
            if ((int) $service->currency_id !== $currencyId) {
                throw new FinanceException('عملة التوزيع يجب أن تطابق عملة الخدمة والدفعة.');
            }
            if (Money::cmp($allocated, $service->remainingAmount()) > 0) {
                throw new FinanceException('لا يجوز دفع مبلغ أكبر من المتبقي على الخدمة «'.$service->title.'».');
            }

            $sum = Money::add($sum, $allocated);
        }

        if (Money::cmp($sum, $amount) !== 0) {
            throw new FinanceException('مجموع التوزيع يجب أن يساوي مبلغ الدفعة بالكامل. لا يُسمح برصيد دائن للعميل.');
        }
    }
}
