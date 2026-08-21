<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSetting extends Model
{
    protected $table = 'home_settings';

    protected $fillable = [
        'hero_type',
        'hero_media_path',
        'hero_title_ar',
        'hero_title_en',
        'hero_subtitle_ar',
        'hero_subtitle_en',
        'cta_text_ar',
        'cta_text_en',
        'cta_url',
    ];

    public static function get(): self
    {
        $row = static::first();
        if ($row) {
            return $row;
        }
        return static::create(['hero_type' => 'image']);
    }
}
