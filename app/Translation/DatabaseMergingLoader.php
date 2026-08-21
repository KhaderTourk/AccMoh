<?php

namespace App\Translation;

use App\Models\SiteText;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

/**
 * يُحمّل الترجمات من Loader الأصلي ثم يدمج عليها نصوص site_texts من قاعدة البيانات.
 */
class DatabaseMergingLoader implements Loader
{
    public function __construct(
        protected Loader $inner
    ) {}

    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->inner->load($locale, $group, $namespace);

        if (! Schema::hasTable('site_texts')) {
            return $lines;
        }

        try {
            $rows = SiteText::where('key', 'like', ($group === '*' && $namespace === '*') ? '%' : "{$group}.%")
                ->where(function ($q) {
                    $q->whereNotNull('value_ar')->orWhereNotNull('value_en');
                })
                ->get();

            foreach ($rows as $row) {
                $val = $locale === 'ar' ? ($row->value_ar ?: $row->value_en) : ($row->value_en ?: $row->value_ar);
                if ($val === null || $val === '') {
                    continue;
                }
                if ($group === '*' && $namespace === '*') {
                    $lines[$row->key] = $val;
                } else {
                    $prefix = $group . '.';
                    if (str_starts_with($row->key, $prefix)) {
                        $rest = substr($row->key, strlen($prefix));
                        Arr::set($lines, $rest, $val);
                    }
                }
            }
        } catch (\Throwable $e) {
            // تجاهل أي خطأ (مثلاً جدول غير موجود بعد)
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return $this->inner->namespaces();
    }
}
