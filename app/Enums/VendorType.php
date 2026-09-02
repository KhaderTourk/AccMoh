<?php

namespace App\Enums;

enum VendorType: string
{
    case Worker = 'worker';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::Worker => 'موظف',
            self::Supplier => 'مورد',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Worker => 'الموظفين',
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

    public function chargesHeading(): string
    {
        return match ($this) {
            self::Worker => 'المستحقات',
            self::Supplier => 'الخدمات والموارد',
        };
    }

    public function chargeAction(): string
    {
        return match ($this) {
            self::Worker => 'مستحق',
            self::Supplier => 'خدمة / مورد',
        };
    }

    public function chargeFormTitle(bool $exists = false): string
    {
        if ($exists) {
            return match ($this) {
                self::Worker => 'تعديل مستحق',
                self::Supplier => 'تعديل خدمة / مورد',
            };
        }

        return match ($this) {
            self::Worker => 'تسجيل مستحق',
            self::Supplier => 'تسجيل ما تم تلقيه',
        };
    }

    public function chargeDetailsLabel(): string
    {
        return match ($this) {
            self::Worker => 'تفاصيل المستحق',
            self::Supplier => 'تفاصيل الخدمة / المورد',
        };
    }

    public function billedLabel(): string
    {
        return match ($this) {
            self::Worker => 'قيمة المستحقات',
            self::Supplier => 'قيمة ما تم تلقيه',
        };
    }

    public function outstandingLabel(): string
    {
        return match ($this) {
            self::Worker => 'المتبقي له',
            self::Supplier => 'المتبقي للمورد',
        };
    }
}
