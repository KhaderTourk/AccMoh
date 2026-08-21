<?php

namespace App\Services\Finance;

use App\Enums\LoanStatus;
use App\Exceptions\FinanceException;
use App\Models\ClientPayment;
use App\Models\Expense;
use App\Models\FamilyLoan;
use App\Models\FamilyLoanRepayment;
use App\Models\FinancialAuditLog;
use App\Models\FundTransfer;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ReversalService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
    ) {}

    public function reversePayment(ClientPayment $payment, $occurredOn = null): void
    {
        if ($payment->is_reversed) {
            throw new FinanceException('هذه الدفعة ملغاة مسبقاً.');
        }

        DB::transaction(function () use ($payment, $occurredOn) {
            $this->ledger->reverseGroup(
                $payment->ledger_group_id,
                $payment,
                'إلغاء دفعة من '.$payment->payer_name,
                $occurredOn ?? now()->toDateString()
            );
            $payment->update([
                'is_reversed' => true,
                'reversed_at' => now(),
            ]);
            FinancialAuditLog::record('reversed', $payment);
        });
    }

    public function reverseLoan(FamilyLoan $loan, $occurredOn = null): void
    {
        if ($loan->is_reversed) {
            throw new FinanceException('هذا القرض ملغى مسبقاً.');
        }
        if (Money::isPositive($loan->repaidAmount())) {
            throw new FinanceException('لا يمكن إلغاء قرض عليه سداد. ألغِ عمليات السداد أولاً.');
        }

        DB::transaction(function () use ($loan, $occurredOn) {
            $this->ledger->reverseGroup(
                $loan->ledger_group_id,
                $loan,
                'إلغاء قرض عائلي',
                $occurredOn ?? now()->toDateString()
            );
            $loan->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'status' => LoanStatus::Paid,
            ]);
            FinancialAuditLog::record('reversed', $loan);
        });
    }

    public function reverseRepayment(FamilyLoanRepayment $repayment, $occurredOn = null): void
    {
        if ($repayment->is_reversed) {
            throw new FinanceException('عملية السداد ملغاة مسبقاً.');
        }

        DB::transaction(function () use ($repayment, $occurredOn) {
            $loans = $repayment->items()->with('loan')->get()->pluck('loan')->filter();
            $this->ledger->reverseGroup(
                $repayment->ledger_group_id,
                $repayment,
                'إلغاء سداد عائلي',
                $occurredOn ?? now()->toDateString()
            );
            $repayment->update([
                'is_reversed' => true,
                'reversed_at' => now(),
            ]);
            foreach ($loans as $loan) {
                $loan->refreshStatus();
            }
            FinancialAuditLog::record('reversed', $repayment);
        });
    }

    public function reverseExpense(Expense $expense, $occurredOn = null): void
    {
        if ($expense->is_reversed) {
            throw new FinanceException('هذا المصروف ملغى مسبقاً.');
        }

        DB::transaction(function () use ($expense, $occurredOn) {
            $this->ledger->reverseGroup(
                $expense->ledger_group_id,
                $expense,
                'إلغاء مصروف: '.$expense->description,
                $occurredOn ?? now()->toDateString()
            );
            $expense->update([
                'is_reversed' => true,
                'reversed_at' => now(),
            ]);
            FinancialAuditLog::record('reversed', $expense);
        });
    }

    public function reverseTransfer(FundTransfer $transfer, $occurredOn = null): void
    {
        if ($transfer->is_reversed) {
            throw new FinanceException('هذا التحويل ملغى مسبقاً.');
        }

        DB::transaction(function () use ($transfer, $occurredOn) {
            $this->ledger->reverseGroup(
                $transfer->ledger_group_id,
                $transfer,
                'إلغاء تحويل بين طرق الدفع',
                $occurredOn ?? now()->toDateString()
            );
            $transfer->update([
                'is_reversed' => true,
                'reversed_at' => now(),
            ]);
            FinancialAuditLog::record('reversed', $transfer);
        });
    }
}
