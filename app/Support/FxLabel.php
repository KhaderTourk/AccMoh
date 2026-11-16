<?php

namespace App\Support;

use App\Models\CashPayment;

/**
 * سطر العملة الأصلية (16/11/2026): «5 دولار بسعر 3.7» تحت حركة انسجلت بالشيكل
 * وهي انصرفت بعملة ثانية، عشان يبيّن الشيكل من وين إجا. للعرض بس.
 */
class FxLabel
{
    public static function of($model): ?string
    {
        if (! $model || ! $model->fx_currency_id || ! $model->exchange_rate || ! $model->fxCurrency || $model->source_amount === null) {
            return null;
        }

        $cur = DrawerPlaces::curName($model->fxCurrency->code, $model->fxCurrency->name);
        $text = self::num($model->source_amount).' '.$cur.' بسعر '.self::num($model->exchange_rate);
        return $text;
    }

    /** «= 18.50 شيكل · اسم الطرف» تحت حركة الدرج اللي انصرفت بعملة ثانية. */
    public static function drawerNote($model): ?string
    {
        if (! $model instanceof CashPayment || ! $model->fx_currency_id || $model->source_amount === null) {
            return null;
        }

        return '= '.number_format((float) $model->amount, 2).' شيكل'.($model->name ? ' · '.$model->name : '');
    }

    protected static function num($v): string
    {
        $s = number_format((float) $v, 4, '.', ',');

        return rtrim(rtrim($s, '0'), '.');
    }
}
