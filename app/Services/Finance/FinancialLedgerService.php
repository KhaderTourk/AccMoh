<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FinancialLedgerService
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function post(array $lines, ?string $groupId = null): string
    {
        $groupId = $groupId ?: (string) Str::uuid();

        foreach ($lines as $line) {
            $amount = Money::of($line['amount']);
            if (Money::isZero($amount)) {
                continue;
            }

            LedgerEntry::query()->create([
                'group_id' => $groupId,
                'transaction_type' => $line['type'] instanceof TransactionType
                    ? $line['type']
                    : TransactionType::from($line['type']),
                'fund_id' => $line['fund_id'],
                'payment_method_id' => $line['payment_method_id'],
                'currency_id' => $line['currency_id'],
                'amount' => $amount,
                'occurred_on' => $line['occurred_on'],
                'description' => $line['description'],
                'notes' => $line['notes'] ?? null,
                'related_type' => isset($line['related']) ? $line['related']::class : ($line['related_type'] ?? null),
                'related_id' => isset($line['related']) ? $line['related']->getKey() : ($line['related_id'] ?? null),
                'created_by' => Auth::id(),
                'is_reversal' => $line['is_reversal'] ?? false,
                'reverses_entry_id' => $line['reverses_entry_id'] ?? null,
            ]);
        }

        return $groupId;
    }

    public function reverseGroup(string $groupId, Model $related, string $description, $occurredOn): string
    {
        $entries = LedgerEntry::query()
            ->where('group_id', $groupId)
            ->where('is_reversal', false)
            ->orderBy('id')
            ->get();

        $lines = [];
        foreach ($entries as $entry) {
            $lines[] = [
                'type' => TransactionType::Reversal,
                'fund_id' => $entry->fund_id,
                'payment_method_id' => $entry->payment_method_id,
                'currency_id' => $entry->currency_id,
                'amount' => Money::neg($entry->amount),
                'occurred_on' => $occurredOn,
                'description' => $description,
                'related' => $related,
                'is_reversal' => true,
                'reverses_entry_id' => $entry->id,
            ];
        }

        return $this->post($lines);
    }
}
