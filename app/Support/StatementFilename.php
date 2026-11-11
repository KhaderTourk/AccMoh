<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * اسم ملف كشف الحساب (قرار 11/11/2026):
 * «اسم الشخص - كشف حساب - D-M-YYYY لغاية D-M-YYYY.pdf»
 * التواريخ من فلتر الفترة إذا محدد، وإلا من أول لآخر حركة. بشرطات مش سلاشات.
 */
class StatementFilename
{
    /** @param iterable<mixed> $dates تواريخ الحركات اللي بالكشف */
    public static function make(string $name, ?string $from, ?string $to, iterable $dates): string
    {
        $all = [];
        foreach ($dates as $d) {
            if ($d) {
                $all[] = $d instanceof \DateTimeInterface ? Carbon::instance($d)->toDateString() : substr((string) $d, 0, 10);
            }
        }
        sort($all);
        $start = $from ?: ($all[0] ?? null);
        $end = $to ?: (end($all) ?: null);

        $clean = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $name) ?? '');
        $clean = preg_replace('/\s+/u', ' ', $clean) ?: 'كشف';

        $parts = [$clean, 'كشف حساب'];
        if ($start && $end) {
            $parts[] = self::fmt($start).' لغاية '.self::fmt($end);
        } elseif ($start || $end) {
            $parts[] = self::fmt($start ?: $end);
        }

        return implode(' - ', $parts).'.pdf';
    }

    protected static function fmt(string $date): string
    {
        $c = Carbon::parse($date);

        return $c->day.'-'.$c->month.'-'.$c->year;
    }
}
