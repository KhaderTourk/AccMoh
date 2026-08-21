<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SiteText extends Model
{
    protected $fillable = ['group', 'key', 'label_ar', 'label_en', 'value_ar', 'value_en', 'sort_order'];

    public static function getLocalizedValue(string $key, string $locale): ?string
    {
        $row = static::where('key', $key)->first();
        if (!$row) {
            return null;
        }
        $val = $locale === 'ar' ? $row->value_ar : $row->value_en;
        if ($val !== null && $val !== '') {
            return $val;
        }
        return $locale === 'ar' ? $row->value_en : $row->value_ar;
    }

    /**
     * Merge all site_texts into Laravel translator for the given locale.
     */
    public static function mergeIntoTranslator(string $locale): void
    {
        $rows = static::orderBy('group')->orderBy('sort_order')->get();
        $lines = [];
        foreach ($rows as $row) {
            $val = $locale === 'ar' ? $row->value_ar : $row->value_en;
            if ($val === null || $val === '') {
                $val = $locale === 'ar' ? $row->value_en : $row->value_ar;
            }
            if ($val !== null && $val !== '') {
                Arr::set($lines, $row->key, $val);
            }
        }
        if (!empty($lines)) {
            app('translator')->addLines($lines, $locale, '*');
        }
    }
}
