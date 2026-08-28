<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوح',
            self::Partial => 'جزئي',
            self::Paid => 'مغلق',
        };
    }
}
