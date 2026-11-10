<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * حساب الاشتراكات والرواتب الشهرية (قرار 10/11/2026):
 * الشهر الجزئي = المبلغ الشهري ÷ أيام الشهر الفعلية × أيام الشغل، بالكسور بدون تقريب لأقرب شيكل.
 * الشهر بيطلع للتسجيل من أول يوم فيه، ولا إشي بينسجّل لحاله.
 */
class MonthlyPlan
{
    /** Whether any day of the month («Y-m») falls between $from and $to. */
    public static function covers(CarbonInterface $from, ?CarbonInterface $to, string $period): bool
    {
        [$start, $end] = self::bounds($period);

        return $from->toDateString() <= $end->toDateString()
            && (! $to || $to->toDateString() >= $start->toDateString());
    }

    /**
     * Days worked in the month and the month's real length.
     *
     * @return array{0: int, 1: int}
     */
    public static function daysIn(CarbonInterface $from, ?CarbonInterface $to, string $period): array
    {
        [$start, $end] = self::bounds($period);
        $inMonth = (int) $end->format('j');
        if (! self::covers($from, $to, $period)) {
            return [0, $inMonth];
        }
        $a = $from->format('Y-m') === $period ? (int) $from->format('j') : 1;
        $b = $to && $to->format('Y-m') === $period ? (int) $to->format('j') : $inMonth;

        return [max(0, $b - $a + 1), $inMonth];
    }

    /** المبلغ المقترح للشهر، بالكسور (لأقرب قرش). */
    public static function suggested(string|float $amount, CarbonInterface $from, ?CarbonInterface $to, string $period): string
    {
        [$days, $inMonth] = self::daysIn($from, $to, $period);
        if ($days <= 0) {
            return '0.00';
        }
        if ($days >= $inMonth) {
            return Money::of($amount);
        }

        return Money::of(round((float) $amount * $days / $inMonth, 2));
    }

    /**
     * Months from $from up to $until («Y-m»), and not after $to, that are not in $recorded.
     *
     * @param  list<string>  $recorded
     * @return list<string>
     */
    public static function pending(CarbonInterface $from, ?CarbonInterface $to, array $recorded, string $until): array
    {
        $out = [];
        $cursor = CarbonImmutable::parse($from->toDateString())->startOfMonth();
        $last = CarbonImmutable::createFromFormat('Y-m-d', $until.'-01')->startOfDay();
        if ($to && $to->toDateString() < $last->toDateString()) {
            $last = CarbonImmutable::parse($to->toDateString())->startOfMonth();
        }
        while ($cursor->lessThanOrEqualTo($last)) {
            $p = $cursor->format('Y-m');
            if (! in_array($p, $recorded, true)) {
                $out[] = $p;
            }
            $cursor = $cursor->addMonth();
        }

        return $out;
    }

    public static function label(string $period): string
    {
        [$y, $m] = explode('-', $period);

        return ((int) $m).'/'.$y;
    }

    /** «20 يوم من 30» للشهر الجزئي، وفاضي للشهر الكامل. */
    public static function daysNote(CarbonInterface $from, ?CarbonInterface $to, string $period): string
    {
        [$days, $inMonth] = self::daysIn($from, $to, $period);

        return $days < $inMonth ? " ({$days} يوم من {$inMonth})" : '';
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    protected static function bounds(string $period): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();

        return [$start, $start->endOfMonth()->startOfDay()];
    }
}
