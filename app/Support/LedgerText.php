<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\FundTransfer;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;

/**
 * بيان أوضح للحركات (12/11/2026، أسلوب الأدراج على كل النظام):
 * «تحويل لبنك فلسطين»، «تحويل من نقدي (دولار ← شيكل)»، «تحويل عملات: شيكل ← دولار» بدل «تحويل صادر» و«صرف عملة (صادر)».
 * للعرض بس: البيان المحفوظ ما بيتغيّر.
 */
class LedgerText
{
    protected static ?array $methods = null;

    protected static ?array $currencies = null;

    public static function for(LedgerEntry $e): string
    {
        $t = $e->relationLoaded('related') || $e->related_type ? $e->related : null;
        if (! $t instanceof FundTransfer) {
            return (string) $e->description;
        }
        if ($e->transaction_type === TransactionType::TransferFee) {
            return 'رسوم تحويل';
        }
        self::$methods ??= PaymentMethod::query()->pluck('name', 'id')->all();
        self::$currencies ??= Currency::query()->get()->mapWithKeys(fn ($c) => [$c->id => DrawerPlaces::curName($c->code, $c->name)])->all();

        $pair = $t->to_currency_id && (int) $t->to_currency_id !== (int) $t->currency_id
            ? (self::$currencies[$t->currency_id] ?? '').' ← '.(self::$currencies[$t->to_currency_id] ?? '')
            : null;
        if ((int) $t->from_payment_method_id === (int) $t->to_payment_method_id) {
            return 'تحويل عملات'.($pair ? ': '.$pair : '');
        }
        $out = (float) $e->amount < 0;
        $other = self::$methods[$out ? $t->to_payment_method_id : $t->from_payment_method_id] ?? 'درج ثاني';

        return ($out ? 'تحويل ل' : 'تحويل من ').$other.($pair ? ' ('.$pair.')' : '');
    }
}
