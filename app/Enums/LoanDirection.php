<?php

namespace App\Enums;

enum LoanDirection: string
{
    case Borrowed = 'borrowed';
    case Lent = 'lent';

    public function label(): string
    {
        return match ($this) {
            self::Borrowed => 'دائن',
            self::Lent => 'مدين',
        };
    }
}
