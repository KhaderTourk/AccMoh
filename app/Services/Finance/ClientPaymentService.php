<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Currency;
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
     * Record a payment against the client's account (including deposits before any service).
     *
     * @param  array<int, mixed>  $allocations  Ignored — kept for older offline payloads.
     */
    public function receive(array $data, array $allocations = []): ClientPayment
    {
        $resolved = $this->resolvePricing($data);

        return DB::transaction(function () use ($data, $resolved) {
            $client = Client::query()->findOrFail($data['client_id']);
            $fund = Fund::business();

            $payment = ClientPayment::query()->create([
                'client_id' => $client->id,
                'fund_id' => $fund->id,
                'amount' => $resolved['amount'],
                'source_amount' => $resolved['source_amount'],
                'exchange_rate' => $resolved['exchange_rate'],
                'fx_currency_id' => $resolved['fx_currency_id'],
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
                'amount' => $resolved['amount'],
                'occurred_on' => $data['payment_date'],
                'description' => 'دفعة من العميل '.$client->name,
                'notes' => $data['notes'] ?? null,
                'related' => $payment,
            ]]);

            $payment->update(['ledger_group_id' => $groupId]);
            FinancialAuditLog::record('created', $payment, ['amount' => $resolved['amount']]);

            return $payment->fresh(['client', 'currency', 'paymentMethod', 'fxCurrency']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{amount: string, source_amount: string|null, exchange_rate: mixed, fx_currency_id: int|null}
     */
    protected function resolvePricing(array $data): array
    {
        $hasFx = filled($data['source_amount'] ?? null) && filled($data['exchange_rate'] ?? null);

        if ($hasFx) {
            $source = $data['source_amount'];
            $rate = $data['exchange_rate'];
            if (! is_numeric($source) || ! Money::isPositive($source)) {
                throw new FinanceException('المبلغ بالدولار يجب أن يكون أكبر من صفر.');
            }
            if (! is_numeric($rate) || (float) $rate <= 0) {
                throw new FinanceException('سعر الدولار يجب أن يكون أكبر من صفر.');
            }

            $amount = Money::mul($source, $rate);
            if (! Money::isPositive($amount)) {
                throw new FinanceException('الإجمالي بالشيكل يجب أن يكون أكبر من صفر.');
            }

            return [
                'amount' => $amount,
                'source_amount' => Money::of($source),
                'exchange_rate' => $rate,
                'fx_currency_id' => $data['fx_currency_id'] ?? Currency::query()->where('code', 'USD')->value('id'),
            ];
        }

        $amount = Money::of($data['amount'] ?? 0);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('مبلغ الدفعة يجب أن يكون أكبر من صفر.');
        }

        return [
            'amount' => $amount,
            'source_amount' => null,
            'exchange_rate' => null,
            'fx_currency_id' => null,
        ];
    }
}
