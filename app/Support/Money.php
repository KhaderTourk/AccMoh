<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public const SCALE = 2;

    public static function of(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, self::SCALE, '.', '');
        }

        $normalized = trim((string) $value);
        $normalized = str_replace([',', ' '], ['', ''], $normalized);

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("قيمة مالية غير صالحة: {$value}");
        }

        return bcadd($normalized, '0', self::SCALE);
    }

    public static function mul(mixed $a, mixed $b): string
    {
        $left = self::numericString($a);
        $right = self::numericString($b);

        return bcadd(bcmul($left, $right, 8), '0', self::SCALE);
    }

    public static function add(mixed $a, mixed $b): string
    {
        return bcadd(self::of($a), self::of($b), self::SCALE);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    public static function neg(mixed $a): string
    {
        return bcmul(self::of($a), '-1', self::SCALE);
    }

    public static function cmp(mixed $a, mixed $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function isZero(mixed $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(mixed $a): bool
    {
        return self::cmp($a, '0') > 0;
    }

    public static function isNegative(mixed $a): bool
    {
        return self::cmp($a, '0') < 0;
    }

    public static function abs(mixed $a): string
    {
        return self::isNegative($a) ? self::neg($a) : self::of($a);
    }

    public static function min(mixed $a, mixed $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::of($a) : self::of($b);
    }

    public static function sum(iterable $values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return $total;
    }

    protected static function numericString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $normalized = trim(str_replace([',', ' '], ['', ''], (string) $value));
        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("قيمة مالية غير صالحة: {$value}");
        }

        return $normalized;
    }
}
