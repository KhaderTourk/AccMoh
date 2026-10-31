<?php

namespace App\Models;

use App\Enums\VendorType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasBalanceMemos;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use BelongsToTenant;
    use HasBalanceMemos;
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'phone', 'job_title', 'work_description', 'salary_amount', 'salary_from', 'salary_to', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => VendorType::class,
            'is_active' => 'boolean',
            'salary_amount' => 'decimal:2',
            'salary_from' => 'date',
            'salary_to' => 'date',
        ];
    }

    public function getMorphClass()
    {
        return 'vendor';
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(VendorCharge::class);
    }

    public function cashPayments(): MorphMany
    {
        return $this->morphMany(CashPayment::class, 'party');
    }

    public function goodsTakes(): MorphMany
    {
        return $this->morphMany(ClientGoodsTake::class, 'party');
    }

    public function hasFinancialHistory(): bool
    {
        return $this->cashPayments()->exists() || $this->charges()->exists() || $this->expenses()->exists() || $this->goodsTakes()->exists() || $this->balanceMemos()->exists();
    }

    /** Salary amount when one is set and running today, else null. */
    public function activeSalary(): ?string
    {
        if (! $this->salary_amount || ! Money::isPositive($this->salary_amount)) {
            return null;
        }
        $today = now()->toDateString();
        if ($this->salary_from && $this->salary_from->toDateString() > $today) {
            return null;
        }
        if ($this->salary_to && $this->salary_to->toDateString() < $today) {
            return null;
        }

        return Money::of($this->salary_amount);
    }

    public function scopeActive(Builder $query): Builder
    {
        if (\App\Support\ArchiveVisibility::showing()) {
            return $query;
        }

        return $query->where($query->qualifyColumn('is_active'), true);
    }

    public function scopeOfType(Builder $query, VendorType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function billedAmount(int $currencyId, ?string $from = null, ?string $to = null): string
    {
        return Money::of(
            $this->charges()
                ->where('currency_id', $currencyId)
                ->tap(fn ($q) => DateRange::constrain($q, 'charge_date', $from, $to))
                ->sum('amount')
        );
    }

    public function paidAmount(int $currencyId, ?string $from = null, ?string $to = null): string
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

    public function outstandingAmount(int $currencyId): string
    {
        return Money::sub(
            Money::sub(
                Money::sub($this->billedAmount($currencyId), $this->paidAmount($currencyId)),
                $this->goodsTakenAmount($currencyId)
            ),
            $this->memoDelta($currencyId)
        );
    }
}
