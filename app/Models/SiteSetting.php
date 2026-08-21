<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $table = 'site_settings';

    protected $fillable = [
        'logo_path',
        'logo_dark_path',
        'favicon_path',
        'action_color',
        'contact_email',
        'contact_phone',
        'address_ar',
        'address_en',
        'maps_url',
        'facebook_url',
        'twitter_url',
        'instagram_url',
        'linkedin_url',
        'default_locale',
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
            'default_locale' => 'ar',
        ]);
    }
}
