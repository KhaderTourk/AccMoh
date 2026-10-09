<?php

namespace App\Services\Finance;

use App\Enums\BalanceMemoLeg;
use App\Enums\BalanceMemoReason;
use App\Models\FinancialAuditLog;
use App\Models\PartyBalanceMemo;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartyBalanceMemoService
{
    public function create(array $data): PartyBalanceMemo
    {
        return DB::transaction(function () use ($data) {
            $memo = PartyBalanceMemo::query()->create([
                'party_type' => $data['party_type'],
                'party_id' => $data['party_id'],
                'direction' => $data['direction'],
                'reason' => $data['reason'],
                'leg' => BalanceMemoLeg::Single,
                'amount' => Money::of($data['amount']),
                'currency_id' => $data['currency_id'],
                'memo_date' => $data['memo_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            FinancialAuditLog::record('created', $memo, ['amount' => $memo->amount]);

            return $memo;
        });
    }

    /**
     * Move the same claim from one party to another. Cash is not posted.
     */
    public function transfer(array $data): PartyBalanceMemo
    {
        return DB::transaction(function () use ($data) {
            $groupId = (string) Str::uuid();
            $shared = [
                'direction' => $data['direction'],
                'reason' => BalanceMemoReason::AccountTransfer,
                'group_id' => $groupId,
                'amount' => Money::of($data['amount']),
                'currency_id' => $data['currency_id'],
                'memo_date' => $data['memo_date'],
                'notes' => $data['notes'] ?? null,
            ];

            $source = PartyBalanceMemo::query()->create($shared + [
                'party_type' => $data['party_type'],
                'party_id' => $data['party_id'],
                'leg' => BalanceMemoLeg::Source,
            ]);
            PartyBalanceMemo::query()->create($shared + [
                'party_type' => $data['target_party_type'],
                'party_id' => $data['target_party_id'],
                'leg' => BalanceMemoLeg::Target,
            ]);

            FinancialAuditLog::record('created', $source, ['amount' => $source->amount, 'group_id' => $groupId]);

            return $source;
        });
    }

    public function update(PartyBalanceMemo $memo, array $data): PartyBalanceMemo
    {
        return DB::transaction(function () use ($memo, $data) {
            $shared = [
                'direction' => $data['direction'],
                'amount' => Money::of($data['amount']),
                'currency_id' => $data['currency_id'],
                'memo_date' => $data['memo_date'],
                'notes' => $data['notes'] ?? null,
            ];

            if ($memo->group_id) {
                PartyBalanceMemo::query()->where('group_id', $memo->group_id)->update($shared);
            } else {
                $memo->update($shared + [
                    'reason' => $data['reason'],
                ]);
            }

            FinancialAuditLog::record('updated', $memo->refresh());

            return $memo;
        });
    }

    public function delete(PartyBalanceMemo $memo): void
    {
        DB::transaction(function () use ($memo) {
            $rows = $memo->group_id
                ? PartyBalanceMemo::query()->where('group_id', $memo->group_id)->get()
                : collect([$memo]);

            foreach ($rows as $row) {
                $row->delete();
                FinancialAuditLog::record('deleted', $row);
            }
        });
    }
}
