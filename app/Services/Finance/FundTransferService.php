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

        if (! Money::isPositive($amount)) {
            throw new FinanceException('مبلغ التحويل يجب أن يكون أكبر من صفر.');
        }
        if (Money::isNegative($fee)) {
            throw new FinanceException('رسوم التحويل لا يجوز أن تكون سالبة.');
        }
        if ((int) $data['from_payment_method_id'] === (int) $data['to_payment_method_id']) {
            throw new FinanceException('طريقة الدفع المصدر يجب أن تختلف عن الوجهة.');
        }

        return DB::transaction(function () use ($data, $amount, $fee) {
            $needed = Money::add($amount, $fee);
            $this->balances->assertSufficient(
                (int) $data['fund_id'],
                (int) $data['from_payment_method_id'],
                (int) $data['currency_id'],
                $needed
            );

            $beforeTotal = $this->balances->fundCash((int) $data['fund_id'], (int) $data['currency_id']);

            $transfer = FundTransfer::query()->create([
                'fund_id' => $data['fund_id'],
                'from_payment_method_id' => $data['from_payment_method_id'],
                'to_payment_method_id' => $data['to_payment_method_id'],
                'amount' => $amount,
                'currency_id' => $data['currency_id'],
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
                    'currency_id' => $data['currency_id'],
                    'amount' => Money::neg($amount),
                    'occurred_on' => $data['transfer_date'],
                    'description' => 'تحويل صادر',
                    'notes' => $data['notes'] ?? null,
                    'related' => $transfer,
                ],
                [
                    'type' => TransactionType::TransferIn,
                    'fund_id' => $data['fund_id'],
                    'payment_method_id' => $data['to_payment_method_id'],
                    'currency_id' => $data['currency_id'],
                    'amount' => $amount,
                    'occurred_on' => $data['transfer_date'],
                    'description' => 'تحويل وارد',
                    'notes' => $data['notes'] ?? null,
                    'related' => $transfer,
                ],
            ];

            if (Money::isPositive($fee)) {
                $lines[] = [
                    'type' => TransactionType::TransferFee,
                    'fund_id' => $data['fund_id'],
                    'payment_method_id' => $data['from_payment_method_id'],
                    'currency_id' => $data['currency_id'],
                    'amount' => Money::neg($fee),
                    'occurred_on' => $data['transfer_date'],
                    'description' => 'رسوم تحويل',
                    'related' => $transfer,
                ];
            }

            $groupId = $this->ledger->post($lines);
            $transfer->update(['ledger_group_id' => $groupId]);

            $afterTotal = $this->balances->fundCash((int) $data['fund_id'], (int) $data['currency_id']);
            $expected = Money::sub($beforeTotal, $fee);
            if (Money::cmp($afterTotal, $expected) !== 0) {
                throw new FinanceException('فشل التحقق من اتساق التحويل. لم يُحفظ أي تغيير.');
            }

            FinancialAuditLog::record('created', $transfer, ['amount' => $amount, 'fee' => $fee]);

            return $transfer->fresh(['fund', 'fromMethod', 'toMethod', 'currency']);
        });
    }
}
