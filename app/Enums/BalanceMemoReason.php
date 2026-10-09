<?php

namespace App\Enums;

enum BalanceMemoReason: string
{
    case Opening = 'opening';
    case YearRollover = 'year_rollover';
    case AccountTransfer = 'account_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'رصيد افتتاحي',
            self::YearRollover => 'ترحيل سنة',
            self::AccountTransfer => 'ترحيل من حساب',
        };
    }
}
