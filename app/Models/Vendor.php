<?php

namespace App\Models;

use App\Enums\VendorType;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'phone', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => VendorType::class,
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, VendorType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function paidAmount(int $currencyId): string
    {
        return Money::of(
            $this->expenses()
                ->active()
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
    }
}
