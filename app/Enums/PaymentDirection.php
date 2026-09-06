<?php

namespace App\Enums;

enum PaymentDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'دفعة واردة',
            self::Outgoing => 'دفعة صادرة',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Incoming => 'text-emerald-600',
            self::Outgoing => 'text-rose-600',
        };
    }

    public function rowClass(): string
    {
        return match ($this) {
            self::Incoming => 'bg-emerald-50/70 dark:bg-emerald-900/20',
            self::Outgoing => 'bg-rose-50/70 dark:bg-rose-900/20',
        };
    }
}
