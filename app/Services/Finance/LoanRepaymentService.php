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
            throw new FinanceException('المبلغ يجب أن يكون أكبر من صفر.');
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

            $allocations = $this->normalizeAllocations($member, $direction, (int) $data['currency_id'], $amount, $allocations);
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
                    ? 'تسوية دائن لـ '.$member->name
                    : 'تسوية مدين من '.$member->name,
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
     * Auto FIFO-allocate when allocations are empty or all zeros.
     *
     * @param  array<int, array{family_loan_id:int, amount:mixed}>  $allocations
     * @return array<int, array{family_loan_id:int, amount:string}>
     */
    protected function normalizeAllocations(
        FamilyMember $member,
        LoanDirection $direction,
        int $currencyId,
        string $amount,
        array $allocations
    ): array {
        $positive = [];
        foreach ($allocations as $row) {
            $allocated = Money::of($row['amount'] ?? 0);
            if (! Money::isPositive($allocated)) {
                continue;
            }
            $positive[] = [
                'family_loan_id' => (int) $row['family_loan_id'],
                'amount' => $allocated,
            ];
        }

        if ($positive !== []) {
            return $positive;
        }

        $left = $amount;
        $auto = [];
        $loans = FamilyLoan::query()
            ->active()
            ->where('family_member_id', $member->id)
            ->where('direction', $direction->value)
            ->where('currency_id', $currencyId)
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('loan_date')
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            if (! Money::isPositive($left)) {
                break;
            }
            $remaining = $loan->remainingAmount();
            if (! Money::isPositive($remaining)) {
                continue;
            }
            $take = Money::min($left, $remaining);
            $auto[] = [
                'family_loan_id' => $loan->id,
                'amount' => $take,
            ];
            $left = Money::sub($left, $take);
        }

        if (Money::isPositive($left)) {
            throw new FinanceException('تعذّر التوزيع التلقائي: لا توجد حركات دائن/مدين مفتوحة كافية لتغطية المبلغ.');
        }

        if ($auto === []) {
            throw new FinanceException('لا توجد حركات دائن/مدين مفتوحة للتوزيع عليها.');
        }

        return $auto;
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
            throw new FinanceException('يجب تحديد الحركة أو الحركات المراد تسويتها.');
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

            $loanId = (int) $row['family_loan_id'];
            if (isset($seen[$loanId])) {
                throw new FinanceException('لا يجوز تكرار الحركة في التوزيع.');
            }
            $seen[$loanId] = true;

            $loan = FamilyLoan::query()->active()->find($loanId);
            if (! $loan) {
                throw new FinanceException('الحركة المحددة غير موجودة أو ملغاة.');
            }
            if ((int) $loan->family_member_id !== (int) $member->id) {
                throw new FinanceException('الحركة لا تخص الفرد المحدد.');
            }
            if ($loan->direction !== $direction) {
                throw new FinanceException('اتجاه الحركة لا يطابق عملية التسوية.');
            }
            if ((int) $loan->currency_id !== $currencyId) {
                throw new FinanceException('عملة التسوية يجب أن تطابق عملة الحركة.');
            }
            if (Money::cmp($allocated, $loan->remainingAmount()) > 0) {
                throw new FinanceException('لا يجوز تسوية مبلغ أكبر من المتبقي على الحركة.');
            }

            $sum = Money::add($sum, $allocated);
        }

        if (Money::cmp($sum, $amount) !== 0) {
            throw new FinanceException('مجموع التوزيع يجب أن يساوي المبلغ بالكامل.');
        }
    }
}
