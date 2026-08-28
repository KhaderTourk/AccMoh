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
use App\Support\IlsFx;
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
        $resolved = IlsFx::resolve($data);
        $amount = $resolved['amount'];

        $direction = $data['direction'] instanceof LoanDirection
            ? $data['direction']
            : LoanDirection::from($data['direction']);

        return DB::transaction(function () use ($data, $resolved, $amount, $direction) {
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
                'source_amount' => $resolved['source_amount'],
                'exchange_rate' => $resolved['exchange_rate'],
                'fx_currency_id' => $resolved['fx_currency_id'],
                'currency_id' => $data['currency_id'],
                'payment_method_id' => $data['payment_method_id'],
                'loan_date' => $data['loan_date'],
                'status' => LoanStatus::Open,
                'notes' => $data['notes'] ?? null,
                'ledger_group_id' => 'pending',
                'is_reversed' => false,
            ]);

            $groupId = $this->postLedger($loan, $member, $fund->id, $data, $amount, $direction);
            $loan->update(['ledger_group_id' => $groupId]);
            FinancialAuditLog::record('created', $loan, ['direction' => $direction->value, 'amount' => $amount]);

            return $loan->fresh(['familyMember', 'currency', 'paymentMethod', 'fxCurrency']);
        });
    }

    public function update(FamilyLoan $loan, array $data): FamilyLoan
    {
        if ($loan->is_reversed) {
            throw new FinanceException('لا يمكن تعديل حركة ملغاة.');
        }
        if (Money::isPositive($loan->repaidAmount())) {
            throw new FinanceException('لا يمكن تعديل حركة عليها تسوية. ألغِ عمليات التسوية أولاً.');
        }

        $resolved = IlsFx::resolve($data);
        $amount = $resolved['amount'];
        $direction = $loan->direction;

        return DB::transaction(function () use ($loan, $data, $resolved, $amount, $direction) {
            $member = FamilyMember::query()->findOrFail($data['family_member_id']);
            $fund = Fund::family();

            $this->ledger->reverseGroup(
                $loan->ledger_group_id,
                $loan,
                'تعديل حركة دائن/مدين',
                $data['loan_date']
            );

            if ($direction === LoanDirection::Lent) {
                $this->balances->assertSufficient(
                    $fund->id,
                    (int) $data['payment_method_id'],
                    (int) $data['currency_id'],
                    $amount
                );
            }

            $loan->update([
                'family_member_id' => $member->id,
                'amount' => $amount,
                'source_amount' => $resolved['source_amount'],
                'exchange_rate' => $resolved['exchange_rate'],
                'fx_currency_id' => $resolved['fx_currency_id'],
                'currency_id' => $data['currency_id'],
                'payment_method_id' => $data['payment_method_id'],
                'loan_date' => $data['loan_date'],
                'notes' => $data['notes'] ?? null,
                'status' => LoanStatus::Open,
            ]);

            $groupId = $this->postLedger($loan->fresh(), $member, $fund->id, $data, $amount, $direction);
            $loan->update(['ledger_group_id' => $groupId]);
            FinancialAuditLog::record('updated', $loan, ['amount' => $amount]);

            return $loan->fresh(['familyMember', 'currency', 'paymentMethod', 'fxCurrency']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function postLedger(
        FamilyLoan $loan,
        FamilyMember $member,
        int $fundId,
        array $data,
        string $amount,
        LoanDirection $direction
    ): string {
        $isBorrowed = $direction === LoanDirection::Borrowed;

        return $this->ledger->post([[
            'type' => $isBorrowed ? TransactionType::FamilyLoanReceived : TransactionType::FamilyLoanGiven,
            'fund_id' => $fundId,
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
    }
}
