<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\CashPayment;
use App\Models\Currency;

/**
 * سعر اليوم لكل عملة بالشيكل: من الإعدادات (rate.USD، rate.JOD)، وإذا مش محدد آخر سعر انستعمل بدفعة.
 */
class Rates
{
    /** @var array<string, float>|null */
    protected static ?array $cache = null;

    /** @return array<string, float> code => شيكل لكل وحدة */
    public static function today(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = ['ILS' => 1.0];
        foreach (Currency::query()->where('code', '!=', 'ILS')->get() as $c) {
            $set = (float) AppSetting::get('rate.'.$c->code, '0');
            if ($set <= 0) {
                $set = (float) CashPayment::query()->where('fx_currency_id', $c->id)->whereNotNull('exchange_rate')
                    ->latest('occurred_on')->latest('id')->value('exchange_rate');
            }
            if ($set > 0) {
                $out[$c->code] = $set;
            }
        }

        return self::$cache = $out;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** المبلغ بالشيكل بسعر اليوم (العملة بدون سعر بتنحسب صفر). */
    public static function toIls(float $amount, string $code): float
    {
        return round($amount * (self::today()[$code] ?? 0), 2);
    }
}
