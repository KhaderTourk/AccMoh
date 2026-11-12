<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** ترتيب الأدراج بكل النظام (12/11/2026): نقدي، بنك فلسطين، بال باي، جوال باي، وبعدهم أي درج جديد. */
    public const ORDER = ['cash', 'bank', 'palpay', 'jawwal_pay'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->ordered();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        $case = 'CASE slug';
        foreach (self::ORDER as $i => $slug) {
            $case .= " WHEN '".$slug."' THEN ".($i + 1);
        }

        return $query->orderByRaw($case.' ELSE '.(count(self::ORDER) + 1).' END')->orderBy('sort_order')->orderBy('id');
    }
}
