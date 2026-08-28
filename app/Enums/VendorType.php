<?php

namespace App\Enums;

enum VendorType: string
{
    case Worker = 'worker';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::Worker => 'عامل',
            self::Supplier => 'مورد',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Worker => 'العمال',
            self::Supplier => 'الموردون',
        };
    }

    public function routePrefix(): string
    {
        return match ($this) {
            self::Worker => 'workers',
            self::Supplier => 'suppliers',
        };
    }
}
