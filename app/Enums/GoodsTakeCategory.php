<?php

namespace App\Enums;

enum GoodsTakeCategory: string
{
    case Discount = 'discount';
    case Goods = 'goods';
    case Service = 'service';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'خصم',
            self::Goods => 'سلعة',
            self::Service => 'خدمة',
            self::Other => 'أخرى',
        };
    }

    public static function fromStoredTitle(?string $title): self
    {
        return match ($title) {
            'خصم' => self::Discount,
            'سلعة' => self::Goods,
            'خدمة' => self::Service,
            default => self::Other,
        };
    }
}
