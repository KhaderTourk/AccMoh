<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\FinancialAuditLog;
use App\Models\FundTransfer;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class FundTransferService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
        protected BalanceService $balances,
    ) {}

    public function transfer(array $data): FundTransfer
    {
        $amount = Money::of($data['amount']);
        $fee = Money::of($data['fee_amount'] ?? 0);
        $fromCurrencyId = (int) $data['currency_id'];
        $toCurrencyId = (int) ($data['to_currency_id'] ?? $fromCurrencyId);
        $isFx = $fromCurrencyId !== $toCurrencyId;

        if (! Money::isPositive($amount)) {
            throw new FinanceException('مبلغ التحويل يجب أن يكون أكبر من صفر.');
        }
        if (Money::isNegative($fee)) {
            throw new FinanceException('رسوم التحويل لا يجوز أن تكون سالبة.');
        }

        if (! $isFx) {
            if ((int) $data['from_payment_method_id'] === (int) $data['to_payment_method_id']) {
                throw new FinanceException('طريقة الدفع المصدر يجب أن تختلف عن الوجهة.');
            }
            $toAmount = $amount;
            $rate = null;
        } else {
            $toAmount = Money::of($data['to_amount'] ?? 0);
            if (! Money::isPositive($toAmount)) {
                throw new FinanceException('المبلغ المستلم بالعملة الأخرى يجب أن يكون أكبر من صفر.');
            }

            $rateInput = $data['exchange_rate'] ?? null;
            if ($rateInput === null || $rateInput === '') {
                // Store as source-units per 1 destination unit (ILS→USD: 365/100 = 3.65)
                $rate = bcdiv($amount, $toAmount, 8);
            } else {
                if ((float) $rateInput <= 0) {
                    throw new FinanceException('سعر التحويل يجب أن يكون أكبر من صفر.');
                }
                $rate = number_format((float) $rateInput, 8, '.', '');
            }
        }

        return DB::transaction(function () use ($data, $amount, $fee, $fromCurrencyId, $toCurrencyId, $toAmount, $rate, $isFx) {
            $needed = Money::add($amount, $fee);
            $this->balances->assertSufficient(
                (int) $data['fund_id'],
                (int) $data['from_payment_method_id'],
                $fromCurrencyId,
                $needed
            );

            $beforeFrom = $this->balances->fundCash((int) $data['fund_id'], $fromCurrencyId);
            $beforeTo = $isFx
                ? $this->balances->fundCash((int) $data['fund_id'], $toCurrencyId)
                : $beforeFrom;

            $transfer = FundTransfer::query()->create([
                'fund_id' => $data['fund_id'],
                'from_payment_method_id' => $data['from_payment_method_id'],
                'to_payment_method_id' => $data['to_payment_method_id'],
                'amount' => $amount,
                'currency_id' => $fromCurrencyId,
                'to_currency_id' => $isFx ? $toCurrencyId : null,
                'to_amount' => $isFx ? $toAmount : null,
                'exchange_rate' => $isFx ? $rate : null,
                'fee_amount' => $fee,
                'transfer_date' => $data['transfer_date'],
                'notes' => $data['notes'] ?? null,
                'ledger_group_id' => 'pending',
                'is_reversed' => false,
            ]);

            $lines = [
                [
                    'type' => TransactionType::TransferOut,
                    'fund_id' => $data['fund_id'],
                    'payment_method_id' => $data['from_payment_method_id'],
                    'currency_id' => $fromCurrencyId,
                    'amount' => Money::neg($amount),
                    'occurred_on' => $data['transfer_date'],
                    'description' => $isFx ? 'صرف عملة (صادر)' : 'تحويل صادر',
                    'notes' => $data['notes'] ?? null,
                    'related' => $transfer,
                ],
                [
                    'type' => TransactionType::TransferIn,
                    'fund_id' => $data['fund_id'],
                    'payment_method_id' => $data['to_payment_method_id'],
                    'currency_id' => $toCurrencyId,
                    'amount' => $toAmount,
                    'occurred_on' => $data['transfer_date'],
                    'description' => $isFx ? 'صرف عملة (وارد)' : 'تحويل وارد',
                    'notes' => $data['notes'] ?? null,
                    'related' => $transfer,
                ],
            ];

            if (Money::isPositive($fee)) {
                $lines[] = [
                    'type' => TransactionType::TransferFee,
                    'fund_id' => $data['fund_id'],
                    'payment_method_id' => $data['from_payment_method_id'],
                    'currency_id' => $fromCurrencyId,
                    'amount' => Money::neg($fee),
                    'occurred_on' => $data['transfer_date'],
                    'description' => 'رسوم تحويل',
                    'related' => $transfer,
                ];
            }

            $groupId = $this->ledger->post($lines);
            $transfer->update(['ledger_group_id' => $groupId]);

            $afterFrom = $this->balances->fundCash((int) $data['fund_id'], $fromCurrencyId);
            $expectedFrom = $isFx
                ? Money::sub($beforeFrom, $needed)
                : Money::sub($beforeFrom, $fee);
            if (Money::cmp($afterFrom, $expectedFrom) !== 0) {
                throw new FinanceException('فشل التحقق من اتساق التحويل (العملة المصدر). لم يُحفظ أي تغيير.');
            }

            if ($isFx) {
                $afterTo = $this->balances->fundCash((int) $data['fund_id'], $toCurrencyId);
                $expectedTo = Money::add($beforeTo, $toAmount);
                if (Money::cmp($afterTo, $expectedTo) !== 0) {
                    throw new FinanceException('فشل التحقق من اتساق التحويل (العملة الوجهة). لم يُحفظ أي تغيير.');
                }
            }

            FinancialAuditLog::record('created', $transfer, [
                'amount' => $amount,
                'to_amount' => $toAmount,
                'fee' => $fee,
                'exchange_rate' => $rate,
                'is_fx' => $isFx,
            ]);

            return $transfer->fresh(['fund', 'fromMethod', 'toMethod', 'currency', 'toCurrency']);
        });
    }
}
