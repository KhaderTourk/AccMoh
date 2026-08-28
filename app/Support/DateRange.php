<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class DateRange
{
    /**
     * @return array{0: ?string, 1: ?string}
     */
    public static function fromRequest(?Request $request = null): array
    {
        $request ??= request();
        $presets = function_exists('date_range_presets') ? date_range_presets() : [];
        $key = (string) $request->input('_preset', '');

        if ($key !== '' && isset($presets[$key])) {
            return [$presets[$key]['from'], $presets[$key]['to']];
        }

        $from = self::normalize($request->input('from'));
        $to = self::normalize($request->input('to'));

        if ($from && $to && strcmp($from, $to) > 0) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    public static function normalize(mixed $value): ?string
    {
        if (! filled($value) || is_array($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $date = Carbon::createFromFormat('!Y-m-d', $raw);
                if (! $date || $date->format('Y-m-d') !== $raw) {
                    return null;
                }

                return $date->toDateString();
            }

            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function constrain(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $query->whereDate($column, '<=', $to);
        }

        return $query;
    }

    public static function label(?string $from, ?string $to): string
    {
        if (! $from && ! $to) {
            return 'طوال المدة';
        }
        if ($from && $to) {
            return 'من '.$from.' إلى '.$to;
        }
        if ($from) {
            return 'من '.$from;
        }

        return 'حتى '.$to;
    }
}
