<?php

namespace App\Services\Finance;

use App\Enums\PaymentDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\FamilyMember;
use App\Models\FinancialAuditLog;
use App\Models\Fund;
use App\Models\Person;
use App\Models\Vendor;
use App\Support\Money;
use App\Support\PaymentFx;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CashPaymentService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
        protected BalanceService $balances,
    ) {}

    public function record(array $data): CashPayment
    {
        $direction = $data['direction'] instanceof PaymentDirection
            ? $data['direction']
            : PaymentDirection::from($data['direction']);

        $pricing = PaymentFx::resolve($data);
        $party = $this->resolveParty($data);
        $fundId = (int) ($data['fund_id'] ?? $this->defaultFundId($party));
        $name = trim((string) ($data['name'] ?? $party?->name ?? ''));
        if ($name === '') {
            throw new FinanceException('اسم الدفعة مطلوب.');
        }

        return DB::transaction(function () use ($data, $direction, $pricing, $party, $fundId, $name) {
            if ($direction === PaymentDirection::Outgoing) {
                $this->balances->assertSufficient(
                    $fundId,
                    (int) $data['payment_method_id'],
                    (int) $pricing['currency_id'],
                    $pricing['amount']
                );
            }

            $payment = CashPayment::query()->create([
                'direction' => $direction,
                'fund_id' => $fundId,
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $pricing['currency_id'],
                'amount' => $pricing['amount'],
                'source_amount' => $pricing['source_amount'],
                'exchange_rate' => $pricing['exchange_rate'],
                'fx_currency_id' => $pricing['fx_currency_id'],
                'name' => $name,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'party_type' => $party?->getMorphClass(),
                'party_id' => $party?->getKey(),
                'occurred_on' => $data['occurred_on'] ?? $data['payment_date'] ?? $data['expense_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'ledger_group_id' => 'pending',
                'is_reversed' => false,
            ]);

            $signed = $direction === PaymentDirection::Incoming
                ? $pricing['amount']
                : Money::neg($pricing['amount']);

            $groupId = $this->ledger->post([[
                'type' => $direction === PaymentDirection::Incoming
                    ? TransactionType::IncomingPayment
                    : TransactionType::OutgoingPayment,
                'fund_id' => $fundId,
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $pricing['currency_id'],
                'amount' => $signed,
                'occurred_on' => $payment->occurred_on,
                'description' => $direction->label().' — '.$name,
                'notes' => $data['notes'] ?? null,
                'related' => $payment,
            ]]);

            $payment->update(['ledger_group_id' => $groupId]);
            FinancialAuditLog::record('created', $payment, [
                'direction' => $direction->value,
                'amount' => $pricing['amount'],
            ]);

            return $payment->fresh(['party', 'fund', 'currency', 'paymentMethod', 'fxCurrency']);
        });
    }

    public function update(CashPayment $payment, array $data): CashPayment
    {
        if ($payment->is_reversed) {
            throw new FinanceException('لا يمكن تعديل دفعة ملغاة.');
        }

        return DB::transaction(function () use ($payment, $data) {
            $this->reverse($payment, $data['occurred_on'] ?? now()->toDateString());

            $data['direction'] = $data['direction'] ?? $payment->direction;
            $data['party_type'] = $data['party_type'] ?? $this->partyKey($payment);
            $data['party_id'] = $data['party_id'] ?? $payment->party_id;

            return $this->record($data);
        });
    }

    public function reverse(CashPayment $payment, $occurredOn = null): void
    {
        if ($payment->is_reversed) {
            throw new FinanceException('هذه الدفعة ملغاة مسبقاً.');
        }

        DB::transaction(function () use ($payment, $occurredOn) {
            $this->ledger->reverseGroup(
                $payment->ledger_group_id,
                $payment,
                'إلغاء '.$payment->direction->label().' — '.$payment->name,
                $occurredOn ?? now()->toDateString()
            );
            $payment->update([
                'is_reversed' => true,
                'reversed_at' => now(),
            ]);
            FinancialAuditLog::record('reversed', $payment);
        });
    }

    public function delete(CashPayment $payment): void
    {
        $this->reverse($payment);
    }

    protected function resolveParty(array $data): ?Model
    {
        $type = $data['party_type'] ?? null;
        $id = $data['party_id'] ?? null;
        if (! filled($type) || ! filled($id)) {
            return null;
        }

        return match ($type) {
            'client', Client::class => Client::query()->findOrFail($id),
            'person', Person::class, FamilyMember::class => Person::query()->findOrFail($id),
            'vendor', Vendor::class => Vendor::query()->findOrFail($id),
            default => throw new FinanceException('نوع الطرف غير معروف.'),
        };
    }

    protected function defaultFundId(?Model $party): int
    {
        if ($party instanceof Person) {
            return Fund::family()->id;
        }

        if (tenantBusinessEnabled()) {
            $business = Fund::query()->where('slug', 'business')->first();
            if ($business) {
                return $business->id;
            }
        }

        return Fund::family()->id;
    }

    protected function partyKey(CashPayment $payment): ?string
    {
        return match ($payment->party_type) {
            'client', Client::class => 'client',
            'person', Person::class, FamilyMember::class => 'person',
            'vendor', Vendor::class => 'vendor',
            default => $payment->party_type,
        };
    }
}
