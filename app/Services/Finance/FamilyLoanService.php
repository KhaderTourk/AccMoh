<?php

namespace App\Services\Finance;

use App\Enums\LoanDirection;
use App\Enums\LoanStatus;
use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\FamilyLoan;
use App\Models\FamilyMember;
use App\Models\FinancialAuditLog;
use App\Models\Fund;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class FamilyLoanService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
        protected BalanceService $balances,
    ) {}

    public function create(array $data): FamilyLoan
    {
        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('المبلغ يجب أن يكون أكبر من صفر.');
        }

        $direction = $data['direction'] instanceof LoanDirection
            ? $data['direction']
            : LoanDirection::from($data['direction']);

        return DB::transaction(function () use ($data, $amount, $direction) {
            $member = FamilyMember::query()->findOrFail($data['family_member_id']);
            $fund = Fund::family();

            if ($direction === LoanDirection::Lent) {
                $this->balances->assertSufficient(
                    $fund->id,
                    (int) $data['payment_method_id'],
                    (int) $data['currency_id'],
                    $amount
                );
            }

            $loan = FamilyLoan::query()->create([
                'family_member_id' => $member->id,
                'fund_id' => $fund->id,
                'direction' => $direction,
                'amount' => $amount,
                'currency_id' => $data['currency_id'],
                'payment_method_id' => $data['payment_method_id'],
                'loan_date' => $data['loan_date'],
                'status' => LoanStatus::Open,
                'notes' => $data['notes'] ?? null,
                'ledger_group_id' => 'pending',
                'is_reversed' => false,
            ]);

            $isBorrowed = $direction === LoanDirection::Borrowed;
            $groupId = $this->ledger->post([[
                'type' => $isBorrowed ? TransactionType::FamilyLoanReceived : TransactionType::FamilyLoanGiven,
                'fund_id' => $fund->id,
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $data['currency_id'],
                'amount' => $isBorrowed ? $amount : Money::neg($amount),
                'occurred_on' => $data['loan_date'],
                'description' => $isBorrowed
                    ? 'دائن من '.$member->name
                    : 'مدين لـ '.$member->name,
                'notes' => $data['notes'] ?? null,
                'related' => $loan,
            ]]);

            $loan->update(['ledger_group_id' => $groupId]);
            FinancialAuditLog::record('created', $loan, ['direction' => $direction->value, 'amount' => $amount]);

            return $loan->fresh(['familyMember', 'currency', 'paymentMethod']);
        });
    }
}
