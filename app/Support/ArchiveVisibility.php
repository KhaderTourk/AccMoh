<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * «إظهار المؤرشف» (قرار 9/11/2026): خيار بالإعدادات بيضل محفوظ، ولما يكون شغّال
 * المؤرشفين بيظهروا بكل القوائم والحركات. الـAPI بتقدر تشغّله لطلب واحد عن طريق الجلسة.
 */
class ArchiveVisibility
{
    public const SESSION_KEY = 'cp.show_archived';

    public const SETTING_KEY = 'show_archived';

    public static function showing(): bool
    {
        return (bool) session(self::SESSION_KEY, false) || self::saved();
    }

    /** القيمة المحفوظة بالإعدادات. */
    public static function saved(): bool
    {
        // بنقرأها مرة وحدة لكل طلب.
        $memo = request()->attributes;
        if (! $memo->has(self::SETTING_KEY)) {
            try {
                $memo->set(self::SETTING_KEY, AppSetting::get(self::SETTING_KEY) === '1');
            } catch (\Throwable $e) {
                $memo->set(self::SETTING_KEY, false);
            }
        }

        return (bool) $memo->get(self::SETTING_KEY);
    }

    public static function toggle(): bool
    {
        $showing = ! self::showing();
        AppSetting::put(self::SETTING_KEY, $showing ? '1' : '0');
        session()->forget(self::SESSION_KEY);
        request()->attributes->set(self::SETTING_KEY, $showing);

        return $showing;
    }
}
