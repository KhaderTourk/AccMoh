<?php

namespace App\Enums;

enum ClientServiceStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::InProgress => 'قيد التنفيذ',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function countsTowardReceivable(): bool
    {
        return $this !== self::Cancelled;
    }
}
