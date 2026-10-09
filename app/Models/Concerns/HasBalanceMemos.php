<?php

namespace App\Models\Concerns;

use App\Models\PartyBalanceMemo;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasBalanceMemos
{
    public function balanceMemos(): MorphMany
    {
        return $this->morphMany(PartyBalanceMemo::class, 'party');
    }

    public function memoDelta(int $currencyId, ?string $from = null, ?string $to = null, bool $before = false): string
    {
        $query = $this->balanceMemos()->where('currency_id', $currencyId);
        if ($before) {
            if (! $from) {
                return '0.00';
            }
            $query->tap(fn ($q) => DateRange::before($q, 'memo_date', $from));
        } else {
            $query->tap(fn ($q) => DateRange::constrain($q, 'memo_date', $from, $to));
        }

        $total = $query
            ->selectRaw('COALESCE(SUM('.PartyBalanceMemo::signedAmountSql().'), 0) as signed_total')
            ->first()
            ?->signed_total;

        return Money::of($total);
    }
}
