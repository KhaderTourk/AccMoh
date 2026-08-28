<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\Expense;
use App\Models\FinancialAuditLog;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
        protected BalanceService $balances,
    ) {}

    public function record(array $data): Expense
    {
        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('مبلغ المصروف يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($data, $amount) {
            $this->balances->assertSufficient(
                (int) $data['fund_id'],
                (int) $data['payment_method_id'],
                (int) $data['currency_id'],
                $amount
            );

            $vendorId = $data['vendor_id'] ?? null;
            $payee = $data['payee'] ?? null;
            if ($vendorId && ! filled($payee)) {
                $payee = Vendor::query()->find($vendorId)?->name;
            }

            $expense = Expense::query()->create([
                'fund_id' => $data['fund_id'],
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'vendor_id' => $vendorId,
                'description' => $data['description'],
                'amount' => $amount,
                'currency_id' => $data['currency_id'],
                'payment_method_id' => $data['payment_method_id'],
                'expense_date' => $data['expense_date'],
                'payee' => $payee,
                'notes' => $data['notes'] ?? null,
                'ledger_group_id' => 'pending',
                'is_reversed' => false,
            ]);

            $groupId = $this->ledger->post([[
                'type' => TransactionType::Expense,
                'fund_id' => $data['fund_id'],
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $data['currency_id'],
                'amount' => Money::neg($amount),
                'occurred_on' => $data['expense_date'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'related' => $expense,
            ]]);

            $expense->update(['ledger_group_id' => $groupId]);
            FinancialAuditLog::record('created', $expense, ['amount' => $amount]);

            return $expense->fresh(['fund', 'category', 'currency', 'paymentMethod', 'vendor']);
        });
    }
}
