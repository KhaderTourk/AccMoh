<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class DateRange
{
    public const MIN_YEAR = 1900;

    public const MAX_YEAR = 2100;

    /**
     * Validation rules for a day\month\year form field (already normalized to Y-m-d by middleware).
     *
     * @return array<int, mixed>
     */
    public static function inputRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (self::toValidDate($value) === null) {
                    $fail('التاريخ غير صحيح، أدخله بالصيغة يوم\\شهر\\سنة بين '.self::MIN_YEAR.' و'.self::MAX_YEAR.'.');
                }
            },
        ];
    }

    /**
     * Strict Y-m-d (or day\month\year) value within the supported year range, else null.
     */
    public static function toValidDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $raw = self::asciiDigits(trim($value));
        $stored = self::toStorageDate($raw);
        if ($stored === null && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $match)
            && checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            $stored = $raw;
        }
        if ($stored === null) {
            return null;
        }

        $year = (int) substr($stored, 0, 4);

        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR ? $stored : null;
    }

    public static function asciiDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

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
            $stored = self::toStorageDate($raw);
            if ($stored) {
                return $stored;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $date = Carbon::createFromFormat('!Y-m-d', $raw);
                if (! $date || $date->format('Y-m-d') !== $raw) {
                    return null;
                }

                return $date->toDateString();
            }

            if (preg_match('/^\d{1,2}[\\\\\\/]\d{1,2}[\\\\\\/]\d{4}$/', $raw)) {
                return null;
            }

            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert a day\month\year value (also / . - separators, Arabic-Indic digits) to Y-m-d.
     * Returns null when the value is not in that display shape.
     */
    public static function toStorageDate(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $raw = self::asciiDigits(trim((string) $value));
        if (! preg_match('/^(\d{1,2})[\\\\\\/.\-](\d{1,2})[\\\\\\/.\-](\d{4})$/', $raw, $match)) {
            return null;
        }

        $day = (int) $match[1];
        $month = (int) $match[2];
        $year = (int) $match[3];
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public static function inputValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return self::display(Carbon::instance($value));
        }

        $raw = trim((string) $value);
        $stored = self::toStorageDate($raw);
        if ($stored !== null) {
            return self::display($stored);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $raw, $match)
            && checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return self::display($raw);
        }

        return $raw;
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

    public static function before(Builder $query, string $column, ?string $before): Builder
    {
        if ($before) {
            $query->whereDate($column, '<', $before);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function queryParams(?string $from, ?string $to, array $extra = []): array
    {
        return array_filter(
            array_merge(['from' => $from, 'to' => $to], $extra),
            fn ($value) => $value !== null && $value !== '' && $value !== []
        );
    }

    public static function display(mixed $value, bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse($value);

            // Four backslashes: date() needs "\\" to emit one "\".
            return $date->format($withTime ? 'd\\\\m\\\\Y H:i' : 'd\\\\m\\\\Y');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function label(?string $from, ?string $to): string
    {
        if (! $from && ! $to) {
            return 'طوال المدة';
        }
        if ($from && $to) {
            return 'من '.self::display($from).' إلى '.self::display($to);
        }
        if ($from) {
            return 'من '.self::display($from);
        }

        return 'حتى '.self::display($to);
    }
}
