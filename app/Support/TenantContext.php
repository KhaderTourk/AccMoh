<?php

namespace App\Support;

class TenantContext
{
    protected static ?int $tenantId = null;

    protected static bool $bypassed = false;

    public static function set(?int $tenantId): void
    {
        self::$tenantId = $tenantId;
        self::$bypassed = false;
    }

    public static function id(): ?int
    {
        return self::$tenantId;
    }

    public static function check(): int
    {
        if (! self::$tenantId) {
            throw new \RuntimeException('لا يوجد مستأجر نشط في السياق.');
        }

        return self::$tenantId;
    }

    /** Temporarily disable tenant global scopes (platform admin tools). */
    public static function bypass(bool $on = true): void
    {
        self::$bypassed = $on;
    }

    public static function bypassed(): bool
    {
        return self::$bypassed;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$bypassed = false;
    }
}
