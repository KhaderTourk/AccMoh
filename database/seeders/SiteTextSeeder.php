<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class SiteTextSeeder extends Seeder
{
    public function run(): void
    {
        $groups = config('site_content', []);
        $sortOrder = 0;
        $translator = app('translator');
        foreach ($groups as $groupSlug => $group) {
            foreach ($group['items'] as $key => $item) {
                $ar = $translator->get($key, [], 'ar');
                $en = $translator->get($key, [], 'en');
                $valueAr = (is_string($ar) && $ar !== $key) ? $ar : null;
                $valueEn = (is_string($en) && $en !== $key) ? $en : null;
                SiteText::updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => $groupSlug,
                        'label_ar' => $item['label_ar'] ?? $key,
                        'label_en' => $item['label_en'] ?? $key,
                        'value_ar' => $valueAr,
                        'value_en' => $valueEn,
                        'sort_order' => $sortOrder++,
                    ]
                );
            }
        }
    }
}
