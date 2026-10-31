<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Data for the worker, supplier and person statement PDF, cut by the same period and type
 * filter the page shows.
 */
class PartyStatement
{
    /**
     * @param  array<string, mixed>  $profile
     * @param  Collection<int, array<string, mixed>>  $movements
     * @return array<string, mixed>
     */
    public static function data(Request $request, array $profile, string $name, string $subtitle, Collection $movements): array
    {
        $request->validate([
            'from' => DateRange::inputRules(false),
            'to' => DateRange::inputRules(false),
            'type' => ['nullable', 'in:all,g1,g2,other'],
            'kinds' => ['sometimes', 'array'],
            'kinds.*' => ['string', 'max:255'],
        ]);
        $from = DateRange::normalize($request->input('from'));
        $to = DateRange::normalize($request->input('to'));
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $type = $request->input('type', 'all');
        $kinds = array_values(array_filter((array) $request->input('kinds', [])));
        $passes = fn ($m) => ($type === 'all' || $m['group'] === $type) && (! $kinds || in_array($m['kind'], $kinds, true));

        $opening = [];
        $rows = [];
        foreach ($movements as $m) {
            if (! $passes($m)) {
                continue;
            }
            if ($from && $m['date'] < $from) {
                $opening[$m['currency']] = ($opening[$m['currency']] ?? 0) + $m['delta'];

                continue;
            }
            if ($to && $m['date'] > $to) {
                continue;
            }
            $rows[] = $m;
        }

        // Current balance per currency over everything, whatever the filter.
        $balance = [];
        foreach ($movements as $m) {
            $balance[$m['currency']] = ($balance[$m['currency']] ?? 0) + $m['delta'];
        }

        $base = $profile['kind'] === 'person' ? 'كشف حساب شخصي' : 'كشف حساب';
        $docTitle = match ($type) {
            'g1' => 'كشف '.$profile['g1'],
            'g2' => 'كشف '.$profile['g2'],
            'other' => 'كشف حركات أخرى',
            default => $base,
        };
        if ($kinds) {
            $docTitle .= ' · '.implode('، ', $kinds);
        }

        return [
            'profile' => $profile,
            'name' => $name,
            'subtitle' => $subtitle,
            'rows' => $rows,
            'opening' => $opening,
            'balance' => $balance,
            'from' => $from,
            'to' => $to,
            'filtered' => $type !== 'all' || $kinds,
            'docTitle' => $docTitle,
            'statementNo' => strtoupper(substr($profile['kind'], 0, 1)).(($request->route('vendor') ?? $request->route('person'))?->id ?? 0).'-'.now()->format('ymd'),
            'exportedAt' => format_date(now(), true),
            'pdfOwnHeader' => true,
            'pdfFooter' => '<table width="100%" style="font-family:dejavusans;font-size:8px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:4px;"><tr>'
                .'<td width="60%">'.($profile['legal'] ? 'للاستفسار: م. محمد عمار الشيخ · 0597667671' : '').'</td>'
                .'<td width="40%" style="text-align:left;">صفحة {PAGENO} من {nbpg}</td></tr></table>',
        ];
    }
}
