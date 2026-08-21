<?php

namespace App\Enums;

enum FundSlug: string
{
    case Family = 'family';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Family => 'صندوق العائلة',
            self::Business => 'صندوق العمل',
        };
    }
}
