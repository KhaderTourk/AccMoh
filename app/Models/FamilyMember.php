<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use App\Enums\LoanDirection;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyMember extends Model
{
    use BelongsToTenant;

    use SoftDeletes;

    protected $fillable = ['name', 'relationship', 'phone', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(FamilyLoan::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(FamilyLoanRepayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasFinancialHistory(): bool
    {
        return $this->loans()->exists() || $this->repayments()->exists();
    }

    public function iOweAmount(int $currencyId): string
    {
        return $this->remainingFor(LoanDirection::Borrowed, $currencyId);
    }

    public function theyOweAmount(int $currencyId): string
    {
        return $this->remainingFor(LoanDirection::Lent, $currencyId);
    }

    public function remainingFor(LoanDirection $direction, int $currencyId): string
    {
        $total = '0.00';
        $loans = $this->loans()
            ->active()
            ->where('direction', $direction)
            ->where('currency_id', $currencyId)
            ->get();

        foreach ($loans as $loan) {
            $total = Money::add($total, $loan->remainingAmount());
        }

        return $total;
    }
}
