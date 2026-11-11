<?php

namespace App\Models;

use App\Enums\VendorType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نوع مستحق من الإعدادات: «مستحقات الموظفين» (راتب، مكافأة، عمولة...) و«خدمات الموردين».
 * المستعمل ما بينحذف، بينطفى بس (قرار 11/11/2026).
 */
class VendorChargeType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['vendor_type', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function charges(): HasMany
    {
        return $this->hasMany(VendorCharge::class, 'charge_type_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class, 'charge_type_id');
    }

    public function scopeFor(Builder $q, VendorType|string $type): Builder
    {
        return $q->where('vendor_type', $type instanceof VendorType ? $type->value : $type)
            ->orderBy('sort_order')->orderBy('name');
    }

    /** الأنواع الافتراضية لمستأجر جديد. */
    public static function seedDefaults(): void
    {
        if (static::query()->where('vendor_type', VendorType::Worker->value)->exists()) {
            return;
        }
        foreach (['راتب', 'مكافأة', 'عمولة'] as $i => $name) {
            static::query()->create(['vendor_type' => VendorType::Worker->value, 'name' => $name, 'sort_order' => $i]);
        }
    }
}
