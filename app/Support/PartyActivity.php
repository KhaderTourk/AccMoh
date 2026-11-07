<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Collection;

/**
 * تواريخ النشاط لقوائم الأطراف: آخر دفعة (آخر حركة بتنزّل اللي عليه)، آخر حركة، وأول حركة.
 */
class PartyActivity
{
    /**
     * @param  Collection<int, array<string, mixed>>  $movements  حركات فيها date و delta
     * @param  bool  $flip  true إذا الـdelta موجب = إله (الموظف والمورد)
     * @return array{last_pay: ?string, last_move: ?string, first_move: ?string}
     */
    public static function fromMovements(Collection $movements, bool $flip = false): array
    {
        $dates = $movements->pluck('date')->filter()->sort()->values();
        $pays = $movements->filter(fn ($m) => ($flip ? -$m['delta'] : $m['delta']) < 0)->pluck('date')->filter()->sort();

        return [
            'last_pay' => $pays->last(),
            'last_move' => $dates->last(),
            'first_move' => $dates->first(),
        ];
    }

    /** @return array{last_pay: ?string, last_move: ?string, first_move: ?string} */
    public static function forClient(Client $client): array
    {
        $day = fn ($v) => $v ? substr((string) $v, 0, 10) : null;
        $paid = $client->cashPayments()->active()->incoming()->max('occurred_on');
        $taken = $client->goodsTakes()->max('taken_on');
        $moves = array_filter([
            $day($paid), $day($taken),
            $day($client->services()->max('service_date')),
            $day($client->cashPayments()->active()->max('occurred_on')),
            $day($client->balanceMemos()->max('memo_date')),
        ]);
        $firsts = array_filter([
            $day($client->services()->min('service_date')),
            $day($client->cashPayments()->active()->min('occurred_on')),
            $day($client->goodsTakes()->min('taken_on')),
            $day($client->balanceMemos()->min('memo_date')),
        ]);
        $pays = array_filter([$day($paid), $day($taken)]);

        return [
            'last_pay' => $pays ? max($pays) : null,
            'last_move' => $moves ? max($moves) : null,
            'first_move' => $firsts ? min($firsts) : null,
        ];
    }
}
