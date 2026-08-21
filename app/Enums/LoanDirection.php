<?php

namespace App\Enums;

enum LoanDirection: string
{
    case Borrowed = 'borrowed';
    case Lent = 'lent';

    public function label(): string
    {
        return match ($this) {
            self::Borrowed => 'اقتراض (أنا مدين)',
            self::Lent => 'إقراض (مدين لي)',
        };
    }
}
