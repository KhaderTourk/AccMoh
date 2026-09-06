<?php

namespace App\Support;

use App\Models\Currency;

class PaymentFx
{
    /**
     * If the selected currency is not ILS, treat the submitted amount as the
     * foreign source and convert to ILS using the exchange rate.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrate(array $data): array
    {
        $currency = Currency::query()->findOrFail($data['currency_id']);

        if ($currency->code === 'ILS') {
            $data['source_amount'] = null;
            $data['exchange_rate'] = null;
            $data['fx_currency_id'] = null;

            return $data;
        }

        $source = $data['source_amount'] ?? null;
        $rate = $data['exchange_rate'] ?? null;
        if (! filled($source) || ! filled($rate)) {
            $data['source_amount'] = null;
            $data['exchange_rate'] = null;
            $data['fx_currency_id'] = null;

            return $data;
        }

        $data['source_amount'] = $source;
        $data['fx_currency_id'] = $currency->id;
        $data['currency_id'] = Currency::byCode('ILS')->id;

        return $data;
    }

    /**
     * @return array{amount: string, source_amount: string|null, exchange_rate: mixed, fx_currency_id: int|null, currency_id: int}
     */
    public static function resolve(array $data): array
    {
        $hydrated = self::hydrate($data);
        $pricing = IlsFx::resolve($hydrated);
        $pricing['currency_id'] = (int) $hydrated['currency_id'];

        return $pricing;
    }
}
