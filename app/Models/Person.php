<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'family_members';

    protected $fillable = ['name', 'relationship', 'phone', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getMorphClass()
    {
        return 'person';
    }

    public function cashPayments(): MorphMany
    {
        return $this->morphMany(CashPayment::class, 'party');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(FamilyLoan::class, 'family_member_id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(FamilyLoanRepayment::class, 'family_member_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasFinancialHistory(): bool
    {
        return $this->cashPayments()->exists()
            || $this->loans()->exists()
            || $this->repayments()->exists();
    }

    public function incomingAmount(int $currencyId): string
    {
        return Money::of(
            $this->cashPayments()
                ->incoming()
                ->active()
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
    }

    public function outgoingAmount(int $currencyId): string
    {
        return Money::of(
            $this->cashPayments()
                ->outgoing()
                ->active()
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
    }

    public function netAmount(int $currencyId): string
    {
        return Money::sub($this->incomingAmount($currencyId), $this->outgoingAmount($currencyId));
    }
}
