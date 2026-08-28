<?php

if (!function_exists('localized')) {
    /**
     * Get localized attribute from model (e.g. title_ar / title_en by current locale).
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @param  string  $attribute  Base name without _ar/_en (e.g. 'title', 'name', 'label')
     * @return string|null
     */
    function localized($model, string $attribute): ?string
    {
        if ($model === null) {
            return null;
        }

        $locale = app()->getLocale();
        $suffix = $locale === 'ar' ? '_ar' : '_en';
        $key = $attribute . $suffix;
        $value = $model->getAttribute($key);
        if ($value !== null && (string) $value !== '') {
            return $value;
        }
        $fallbackKey = $locale === 'ar' ? $attribute . '_en' : $attribute . '_ar';
        $fallback = $model->getAttribute($fallbackKey);
        return $fallback !== null ? $fallback : $model->getAttribute($attribute . '_ar');
    }
}

if (!function_exists('localizedStrict')) {
    /**
     * Current-locale field only (no fallback to the other language).
     * Pair with ?? __('...') so empty DB values use translation files for that locale.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     */
    function localizedStrict($model, string $attribute): ?string
    {
        if ($model === null) {
            return null;
        }

        $locale = app()->getLocale();
        $suffix = $locale === 'ar' ? '_ar' : '_en';
        $value = $model->getAttribute($attribute . $suffix);
        if ($value === null) {
            return null;
        }

        return trim((string) $value) !== '' ? (string) $value : null;
    }
}

if (!function_exists('current_slug')) {
    /**
     * Get slug for current locale (slug_ar or slug_en).
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @return string|null
     */
    function current_slug($model): ?string
    {
        if ($model === null) {
            return null;
        }
        $slug = localized($model, 'slug');
        return $slug ?: (string) $model->getKey();
    }
}

if (!function_exists('stat_value')) {
    /**
     * الحصول على قيمة إحصائية قابلة للتحرير مع قيمة بديلة.
     *
     * @param  string  $key  مفتاح الإحصائية (مثل partners_stat2)
     * @param  string|int|null  $fallback  القيمة البديلة إن كانت من site_texts فارغة
     * @return string|null
     */
    function stat_value(string $key, $fallback = null): ?string
    {
        $val = __('stats.' . $key);
        if (trim((string) $val) !== '' && $val !== 'stats.' . $key) {
            return $val;
        }
        return $fallback !== null ? (string) $fallback : null;
    }
}

if (!function_exists('cpCan')) {
    /**
     * Check if current user can access CP permission.
     *
     * @param  string  $permission  Permission slug (e.g. 'settings', 'home')
     * @return bool
     */
    function cpCan(string $permission): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if ($user->is_platform_admin ?? false) {
            return false;
        }
        if ($user->is_super_admin ?? false) {
            return true;
        }
        return $user->canAccess($permission);
    }
}

if (! function_exists('currentTenant')) {
    function currentTenant(): ?\App\Models\Tenant
    {
        $fromRequest = request()->attributes->get('tenant');
        if ($fromRequest instanceof \App\Models\Tenant) {
            return $fromRequest;
        }

        return auth()->user()?->tenant;
    }
}

if (! function_exists('tenantBusinessEnabled')) {
    function tenantBusinessEnabled(): bool
    {
        $tenant = currentTenant();

        return $tenant ? (bool) $tenant->business_enabled : true;
    }
}

if (!function_exists('money_format_currency')) {
    /**
     * Format a money amount with currency symbol.
     */
    function money_format_currency(mixed $amount, ?\App\Models\Currency $currency = null): string
    {
        $value = \App\Support\Money::of($amount);
        if ($currency) {
            return $currency->format($value);
        }

        return number_format((float) $value, 2, '.', ',');
    }
}

if (! function_exists('date_range_presets')) {
    /**
     * @return array<string, array{label: string, from: ?string, to: ?string}>
     */
    function date_range_presets(): array
    {
        $today = now()->toDateString();

        return [
            'current_month' => [
                'label' => 'الشهر الحالي',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => $today,
            ],
            'last_month' => [
                'label' => 'آخر شهر',
                'from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'all' => [
                'label' => 'طوال المدة',
                'from' => null,
                'to' => null,
            ],
        ];
    }
}

if (! function_exists('date_range_url')) {
    function date_range_url(?string $from, ?string $to): string
    {
        $query = request()->except(['from', 'to', '_preset', 'page']);
        if ($from) {
            $query['from'] = $from;
        }
        if ($to) {
            $query['to'] = $to;
        }

        $qs = http_build_query($query);

        return request()->url().($qs !== '' ? '?'.$qs : '');
    }
}

if (! function_exists('date_range_preset_active')) {
    function date_range_preset_active(?string $from, ?string $to, ?string $key = null): bool
    {
        $preset = (string) request('_preset', '');
        if ($key && $preset !== '') {
            return $preset === $key;
        }

        [$currentFrom, $currentTo] = \App\Support\DateRange::fromRequest();

        return $currentFrom === $from && $currentTo === $to;
    }
}

if (!function_exists('localized_route')) {
    /**
     * رابط مسار محلي حسب اللغة الحالية (للمسارات الأمامية فقط).
     *
     * @param  string  $name  اسم المسار
     * @param  array  $parameters
     * @return string
     */
    function localized_route(string $name, array $parameters = []): string
    {
        return \Mcamara\LaravelLocalization\Facades\LaravelLocalization::localizeUrl(route($name, $parameters));
    }
}
