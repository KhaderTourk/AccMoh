<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    public function getMorphClass()
    {
        return 'client';
    }

    public function services(): HasMany
    {
        return $this->hasMany(ClientService::class);
    }

    public function cashPayments(): MorphMany
    {
        return $this->morphMany(CashPayment::class, 'party');
    }

    public function payments(): MorphMany
    {
        return $this->cashPayments()->incoming();
    }

    public function legacyPayments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasFinancialHistory(): bool
    {
        return $this->services()->exists() || $this->cashPayments()->exists() || $this->legacyPayments()->exists();
    }

    public function billedAmount(int $currencyId, ?string $from = null, ?string $to = null): string
    {
        return Money::of(
            $this->services()
                ->billable()
                ->where('currency_id', $currencyId)
                ->tap(fn ($q) => DateRange::constrain($q, 'service_date', $from, $to))
                ->sum('amount')
        );
    }

    public function paidAmount(int $currencyId, ?string $from = null, ?string $to = null): string
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

    public function openingBalance(int $currencyId, ?string $from): string
    {
        if (! $from) {
            return '0.00';
        }

        $billed = Money::of(
            $this->services()
                ->billable()
                ->where('currency_id', $currencyId)
                ->tap(fn ($q) => DateRange::before($q, 'service_date', $from))
                ->sum('amount')
        );
        $paid = Money::of(
            $this->cashPayments()
                ->incoming()
                ->active()
                ->where('currency_id', $currencyId)
                ->tap(fn ($q) => DateRange::before($q, 'occurred_on', $from))
                ->sum('amount')
        );

        return Money::sub($billed, $paid);
    }

    public function outstandingAmount(int $currencyId): string
    {
        return Money::sub($this->billedAmount($currencyId), $this->paidAmount($currencyId));
    }

    public function personName(): string
    {
        if ($this->hasLegacyContactName()) {
            return (string) $this->contact_name;
        }

        return (string) $this->name;
    }

    public function organization(): ?string
    {
        if (filled($this->company_name)) {
            return $this->company_name;
        }

        if ($this->hasLegacyContactName()) {
            return $this->name;
        }

        return null;
    }

    /**
     * Older records stored الجهة in `name` and الاسم in `contact_name`.
     */
    protected function hasLegacyContactName(): bool
    {
        return filled($this->getAttribute('contact_name'))
            && (string) $this->getAttribute('contact_name') !== (string) $this->name;
    }
}
