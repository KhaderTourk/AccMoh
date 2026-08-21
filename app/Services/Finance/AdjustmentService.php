<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Models\FinancialAuditLog;
use App\Models\LedgerEntry;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdjustmentService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
    ) {}

    public function opening(array $data): LedgerEntry
    {
        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('الرصيد الافتتاحي يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($data, $amount) {
            $groupId = (string) Str::uuid();
            $this->ledger->post([[
                'type' => TransactionType::Adjustment,
                'fund_id' => $data['fund_id'],
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $data['currency_id'],
                'amount' => $amount,
                'occurred_on' => $data['occurred_on'],
                'description' => $data['description'] ?? 'رصيد افتتاحي',
                'notes' => $data['notes'] ?? null,
            ]], $groupId);

            $entry = LedgerEntry::query()->where('group_id', $groupId)->firstOrFail();
            FinancialAuditLog::record('created', $entry, ['amount' => $amount]);

            return $entry;
        });
    }
}
