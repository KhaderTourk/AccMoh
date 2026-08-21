<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSetting extends Model
{
    protected $table = 'seo_settings';

    protected $fillable = [
        'site_title_ar',
        'site_title_en',
        'meta_description_ar',
        'meta_description_en',
        'meta_keywords_ar',
        'meta_keywords_en',
        'og_title_ar',
        'og_title_en',
        'og_description_ar',
        'og_description_en',
        'og_image_path',
        'og_type',
        'twitter_card_type',
        'twitter_site',
        'twitter_creator',
        'robots_txt',
        'allow_indexing',
        'custom_meta_tags',
        'google_analytics_id',
        'google_site_verification',
        'bing_verification',
        'organization_schema',
    ];

    protected $casts = [
        'allow_indexing' => 'boolean',
    ];

    /**
     * الحصول على سجل الإعدادات الوحيد (Singleton).
     */
    public static function get(): self
    {
        $row = static::first();
        if ($row) {
            return $row;
        }
        return static::create([
            'og_type' => 'website',
            'twitter_card_type' => 'summary_large_image',
            'allow_indexing' => true,
        ]);
    }
}
