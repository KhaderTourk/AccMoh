<?php

namespace App\Services\Finance;

use App\Enums\LoanDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\FamilyLoan;
use App\Models\FamilyLoanRepayment;
use App\Models\FamilyLoanRepaymentItem;
use App\Models\FamilyMember;
use App\Models\FinancialAuditLog;
use App\Models\Fund;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class LoanRepaymentService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
        protected BalanceService $balances,
    ) {}

    /**
     * @param  array<int, array{family_loan_id:int, amount:mixed}>  $allocations
     */
    public function repay(array $data, array $allocations): FamilyLoanRepayment
    {
        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('مبلغ السداد يجب أن يكون أكبر من صفر.');
        }

        $direction = $data['direction'] instanceof LoanDirection
            ? $data['direction']
            : LoanDirection::from($data['direction']);

        return DB::transaction(function () use ($data, $allocations, $amount, $direction) {
            $member = FamilyMember::query()->findOrFail($data['family_member_id']);
            $fund = Fund::family();

            if ($direction === LoanDirection::Borrowed) {
                $this->balances->assertSufficient(
                    $fund->id,
                    (int) $data['payment_method_id'],
                    (int) $data['currency_id'],
                    $amount
                );
            }

            $this->assertAllocations($member, $direction, (int) $data['currency_id'], $amount, $allocations);

            $repayment = FamilyLoanRepayment::query()->create([
                'family_member_id' => $member->id,
                'fund_id' => $fund->id,
                'direction' => $direction,
                'amount' => $amount,
                'currency_id' => $data['currency_id'],
                'payment_method_id' => $data['payment_method_id'],
                'repayment_date' => $data['repayment_date'],
                'notes' => $data['notes'] ?? null,
                'ledger_group_id' => 'pending',
                'is_reversed' => false,
            ]);

            foreach ($allocations as $row) {
                $allocated = Money::of($row['amount']);
                if (! Money::isPositive($allocated)) {
                    continue;
                }
                FamilyLoanRepaymentItem::query()->create([
                    'family_loan_repayment_id' => $repayment->id,
                    'family_loan_id' => $row['family_loan_id'],
                    'allocated_amount' => $allocated,
                ]);
            }

            $isPayingDebt = $direction === LoanDirection::Borrowed;
            $groupId = $this->ledger->post([[
                'type' => $isPayingDebt
                    ? TransactionType::FamilyLoanRepaymentPaid
                    : TransactionType::FamilyLoanRepaymentReceived,
                'fund_id' => $fund->id,
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $data['currency_id'],
                'amount' => $isPayingDebt ? Money::neg($amount) : $amount,
                'occurred_on' => $data['repayment_date'],
                'description' => $isPayingDebt
                    ? 'سداد قرض لـ '.$member->name
                    : 'استلام سداد من '.$member->name,
                'notes' => $data['notes'] ?? null,
                'related' => $repayment,
            ]]);

            $repayment->update(['ledger_group_id' => $groupId]);

            foreach ($repayment->items as $item) {
                $item->loan->refreshStatus();
            }

            FinancialAuditLog::record('created', $repayment, ['amount' => $amount]);

            return $repayment->fresh(['items.loan', 'familyMember', 'currency', 'paymentMethod']);
        });
    }

    /**
     * @param  array<int, array{family_loan_id:int, amount:mixed}>  $allocations
     */
    protected function assertAllocations(
        FamilyMember $member,
        LoanDirection $direction,
        int $currencyId,
        string $amount,
        array $allocations
    ): void {
        if ($allocations === []) {
            throw new FinanceException('يجب تحديد القرض أو القروض المراد السداد منها.');
        }

        $seen = [];
        $sum = '0.00';

        foreach ($allocations as $row) {
            $allocated = Money::of($row['amount'] ?? 0);
            if (Money::isZero($allocated)) {
                continue;
            }
            if (! Money::isPositive($allocated)) {
                throw new FinanceException('مبلغ توزيع السداد يجب أن يكون أكبر من صفر.');
            }

            $loanId = (int) $row['family_loan_id'];
            if (isset($seen[$loanId])) {
                throw new FinanceException('لا يجوز تكرار القرض في توزيع السداد.');
            }
            $seen[$loanId] = true;

            $loan = FamilyLoan::query()->active()->find($loanId);
            if (! $loan) {
                throw new FinanceException('القرض المحدد غير موجود أو ملغى.');
            }
            if ((int) $loan->family_member_id !== (int) $member->id) {
                throw new FinanceException('القرض لا يخص فرد العائلة المحدد.');
            }
            if ($loan->direction !== $direction) {
                throw new FinanceException('اتجاه القرض لا يطابق عملية السداد.');
            }
            if ((int) $loan->currency_id !== $currencyId) {
                throw new FinanceException('عملة السداد يجب أن تطابق عملة القرض.');
            }
            if (Money::cmp($allocated, $loan->remainingAmount()) > 0) {
                throw new FinanceException('لا يجوز سداد مبلغ أكبر من المتبقي على القرض.');
            }

            $sum = Money::add($sum, $allocated);
        }

        if (Money::cmp($sum, $amount) !== 0) {
            throw new FinanceException('مجموع توزيع السداد يجب أن يساوي مبلغ السداد بالكامل.');
        }
    }
}
