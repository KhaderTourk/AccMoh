<?php

namespace App\Models;

use App\Enums\FundSlug;
use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    protected $fillable = ['name', 'slug'];

    public function slugEnum(): FundSlug
    {
        return FundSlug::from($this->slug);
    }

    public static function family(): self
    {
        return static::query()->where('slug', FundSlug::Family->value)->firstOrFail();
    }

    public static function business(): self
    {
        return static::query()->where('slug', FundSlug::Business->value)->firstOrFail();
    }
}
