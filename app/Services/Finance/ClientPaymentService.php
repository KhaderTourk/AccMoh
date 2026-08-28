<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\FinancialAuditLog;
use App\Models\Fund;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ClientPaymentService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
    ) {}

    /**
     * Record a payment against the client's total outstanding (not a specific service).
     *
     * @param  array<int, mixed>  $allocations  Ignored — kept for older offline payloads.
     */
    public function receive(array $data, array $allocations = []): ClientPayment
    {
        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('مبلغ الدفعة يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($data, $amount) {
            $client = Client::query()->findOrFail($data['client_id']);
            $fund = Fund::business();
            $currencyId = (int) $data['currency_id'];

            $outstanding = $client->outstandingAmount($currencyId);
            if (! Money::isPositive($outstanding)) {
                throw new FinanceException('لا يوجد مبلغ مستحق على العميل بهذه العملة. سجّل خدمة أولاً.');
            }
            if (Money::cmp($amount, $outstanding) > 0) {
                throw new FinanceException('لا يجوز أن تتجاوز الدفعة إجمالي المستحق على العميل.');
            }

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

            return $payment->fresh(['client', 'currency', 'paymentMethod']);
        });
    }
}
