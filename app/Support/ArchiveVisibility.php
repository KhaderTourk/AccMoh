<?php

namespace App\Support;

class ArchiveVisibility
{
    public const SESSION_KEY = 'cp.show_archived';

    public static function showing(): bool
    {
        return (bool) session(self::SESSION_KEY, false);
    }

    public static function toggle(): bool
    {
        $showing = ! self::showing();
        session([self::SESSION_KEY => $showing]);

        return $showing;
    }
}
