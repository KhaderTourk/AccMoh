<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasBalanceMemos;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use BelongsToTenant;
    use HasBalanceMemos;
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

    public function goodsTakes(): MorphMany
    {
        return $this->morphMany(ClientGoodsTake::class, 'party');
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
        if (\App\Support\ArchiveVisibility::showing()) {
            return $query;
        }

        return $query->where($query->qualifyColumn('is_active'), true);
    }

    public function hasFinancialHistory(): bool
    {
        return $this->cashPayments()->exists()
            || $this->loans()->exists()
            || $this->repayments()->exists()
            || $this->goodsTakes()->exists()
            || $this->balanceMemos()->exists();
    }

    public function incomingAmount(int $currencyId, ?string $from = null, ?string $to = null): string
    {
        return Money::of(
            $this->cashPayments()
                ->incoming()
                ->active()
                ->where('currency_id', $currencyId)
                ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to))
                ->sum('amount')
        );
    }

    public function outgoingAmount(int $currencyId, ?string $from = null, ?string $to = null): string
    {
        return Money::of(
            $this->cashPayments()
                ->outgoing()
                ->active()
                ->where('currency_id', $currencyId)
                ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to))
                ->sum('amount')
        );
    }

    public function goodsTakenAmount(int $currencyId, ?string $from = null, ?string $to = null): string
    {
        return Money::of(
            $this->goodsTakes()
                ->where('currency_id', $currencyId)
                ->tap(fn ($q) => DateRange::constrain($q, 'taken_on', $from, $to))
                ->sum('amount')
        );
    }

    public function netAmount(int $currencyId, ?string $from = null, ?string $to = null): string
    {
        return Money::sub(
            Money::add($this->memoDelta($currencyId, $from, $to), $this->outgoingAmount($currencyId, $from, $to)),
            Money::add($this->incomingAmount($currencyId, $from, $to), $this->goodsTakenAmount($currencyId, $from, $to))
        );
    }
}
