<?php

namespace App\Console\Commands;

use App\Enums\BalanceMemoDirection;
use App\Enums\BalanceMemoReason;
use App\Models\Client;
use App\Models\ClientGoodsTake;
use App\Models\FinancialAuditLog;
use App\Models\PartyBalanceMemo;
use App\Models\Person;
use App\Models\Vendor;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #7 §8: a carried-forward balance that was recorded as goods taken («رصيد مرحل») becomes an
 * opening-balance memo with the same party, amount, currency and date. The party's balance must
 * come out identical, otherwise nothing is saved.
 */
class ConvertCarriedGoodsTakes extends Command
{
    protected $signature = 'accounts:convert-carried-goods {--apply : Save the change (without it, only report)}';

    protected $description = 'Turn goods takes titled «رصيد مرحل» into opening-balance memos without changing any balance';

    public function handle(): int
    {
        $rows = ClientGoodsTake::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn(DB::raw('TRIM(title)'), ['رصيد مرحل', 'رصيد مرحّل', 'حساب مرحل', 'حساب مرحّل'])
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to convert.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $type = $row->party_type ?: 'client';
            $id = $row->party_id ?: $row->client_id;
            TenantContext::set((int) $row->tenant_id);
            $party = match ($type) {
                'client' => Client::withoutGlobalScopes()->find($id),
                'vendor' => Vendor::withoutGlobalScopes()->find($id),
                'person' => Person::withoutGlobalScopes()->find($id),
                default => null,
            };
            if (! $party) {
                $this->error("Goods take #{$row->id}: party {$type}:{$id} not found, skipped.");

                continue;
            }

            $before = $this->balance($party, (int) $row->currency_id);
            // Goods taken lower the balance; pick the memo direction that lowers it by the same amount.
            $direction = $type === 'vendor' ? BalanceMemoDirection::OwedToUs : BalanceMemoDirection::OwedByUs;
            $line = sprintf('#%d %s:%d «%s» %s on %s → memo %s, balance %s',
                $row->id, $type, $id, $row->title, $row->amount, $row->taken_on->toDateString(), $direction->value, $before);

            if (! $this->option('apply')) {
                $this->line('[dry run] '.$line);

                continue;
            }

            DB::transaction(function () use ($row, $party, $type, $id, $direction, $before, $line) {
                $memo = PartyBalanceMemo::query()->create([
                    'party_type' => $type,
                    'party_id' => $id,
                    'direction' => $direction,
                    'reason' => BalanceMemoReason::Opening,
                    'amount' => Money::of($row->amount),
                    'currency_id' => $row->currency_id,
                    'memo_date' => $row->taken_on->toDateString(),
                    'notes' => trim(($row->notes ? $row->notes."\n" : '').'محوّل من «'.$row->title.'» المسجّلة كبضاعة'),
                ]);
                $memo->forceFill(['tenant_id' => $row->tenant_id])->save();
                $row->delete();
                FinancialAuditLog::record('deleted', $row, ['converted_to_memo' => $memo->id]);

                $after = $this->balance($party->fresh(), (int) $row->currency_id);
                if (Money::cmp($before, $after) !== 0) {
                    throw new \RuntimeException("Balance changed for {$type}:{$id} ({$before} → {$after}); rolled back.");
                }
                $this->info('converted '.$line.' → memo #'.$memo->id.', balance after '.$after);
            });
        }
        TenantContext::clear();

        return self::SUCCESS;
    }

    protected function balance($party, int $currencyId): string
    {
        return $party instanceof Person ? $party->netAmount($currencyId) : $party->outstandingAmount($currencyId);
    }
}
