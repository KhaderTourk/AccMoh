<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToTenant;

    use SoftDeletes;

    protected $fillable = ['name', 'contact_name', 'phone', 'email', 'company_name', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(ClientService::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasFinancialHistory(): bool
    {
        return $this->services()->exists() || $this->payments()->exists();
    }

    public function billedAmount(int $currencyId): string
    {
        return Money::of(
            $this->services()
                ->billable()
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
    }

    public function paidAmount(int $currencyId): string
    {
        return Money::of(
            $this->payments()
                ->active()
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
    }

    public function outstandingAmount(int $currencyId): string
    {
        return Money::sub($this->billedAmount($currencyId), $this->paidAmount($currencyId));
    }
}
