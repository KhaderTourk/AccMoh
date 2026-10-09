<?php

namespace App\Enums;

enum BalanceMemoDirection: string
{
    case OwedToUs = 'owed_to_us';
    case OwedByUs = 'owed_by_us';

    public function label(): string
    {
        return match ($this) {
            self::OwedToUs => 'عليه',
            self::OwedByUs => 'له',
        };
    }
}
