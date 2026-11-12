<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * أماكن الدرج (الخزنة، المكتب، البيت...): توزيع للعرض بس، محفوظ بإعدادات النظام كـJSON.
 * ما بيدخل بأي حساب ولا رصيد (قرار م. محمد 12/11/2026).
 *
 * @phpstan-type Place array{name: string, amounts: array<string, float>}
 */
class DrawerPlaces
{
    /** اسم العملة القصير بالكروت والنوافذ: شيكل، دولار، دينار. */
    public static function curName(string $code, ?string $fallback = null): string
    {
        return ['ILS' => 'شيكل', 'USD' => 'دولار', 'JOD' => 'دينار'][$code] ?? ($fallback ?? $code);
    }

    public static function key(int $methodId): string
    {
        return 'drawer_places.'.$methodId;
    }

    /** @return list<Place> */
    public static function get(int $methodId): array
    {
        $raw = AppSetting::get(self::key($methodId));
        $list = $raw ? json_decode($raw, true) : [];

        return is_array($list) ? array_values(array_filter($list, fn ($p) => is_array($p) && isset($p['name']))) : [];
    }

    /** @param list<Place> $places */
    public static function put(int $methodId, array $places): void
    {
        AppSetting::put(self::key($methodId), $places ? json_encode(array_values($places), JSON_UNESCAPED_UNICODE) : null);
    }
}
