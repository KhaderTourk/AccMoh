<?php

namespace App\Support;

use App\Exceptions\FinanceException;
use App\Models\Currency;

class IlsFx
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $isFx): array
    {
        return [
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Force ILS posting; keep USD source + rate when FX is enabled.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stamp(array $data, bool $isFx): array
    {
        $data['currency_id'] = Currency::byCode('ILS')->id;
        if ($isFx) {
            $data['fx_currency_id'] = Currency::byCode('USD')->id;
        } else {
            $data['source_amount'] = null;
            $data['exchange_rate'] = null;
            $data['fx_currency_id'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{amount: string, source_amount: string|null, exchange_rate: mixed, fx_currency_id: int|null}
     */
    public static function resolve(array $data): array
    {
        $hasFx = filled($data['source_amount'] ?? null) && filled($data['exchange_rate'] ?? null);

        if ($hasFx) {
            $source = $data['source_amount'];
            $rate = $data['exchange_rate'];
            if (! is_numeric($source) || ! Money::isPositive($source)) {
                throw new FinanceException('المبلغ بالدولار يجب أن يكون أكبر من صفر.');
            }
            if (! is_numeric($rate) || (float) $rate <= 0) {
                throw new FinanceException('سعر الدولار يجب أن يكون أكبر من صفر.');
            }

            $amount = Money::mul($source, $rate);
            if (! Money::isPositive($amount)) {
                throw new FinanceException('الإجمالي بالشيكل يجب أن يكون أكبر من صفر.');
            }

            return [
                'amount' => $amount,
                'source_amount' => Money::of($source),
                'exchange_rate' => $rate,
                'fx_currency_id' => $data['fx_currency_id'] ?? Currency::query()->where('code', 'USD')->value('id'),
            ];
        }

        $amount = Money::of($data['amount'] ?? 0);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('المبلغ يجب أن يكون أكبر من صفر.');
        }

        return [
            'amount' => $amount,
            'source_amount' => null,
            'exchange_rate' => null,
            'fx_currency_id' => null,
        ];
    }
}
