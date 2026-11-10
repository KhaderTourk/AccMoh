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
            'is_pinned' => 'boolean',
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

    /** First month counted by «أضف هالشهر» for salaries and supplier subscriptions. */
    public const MONTHLY_TRACKED_FROM = '2026-11-01';

    /** «راتب» للموظف، و«اشتراك» للمورد. */
    public function monthlyNoun(): string
    {
        return $this->type === VendorType::Worker ? 'راتب' : 'اشتراك';
    }

    /** Whether a monthly salary / subscription amount is set at all. */
    public function hasMonthly(): bool
    {
        return $this->salary_amount && Money::isPositive($this->salary_amount) && $this->salary_from;
    }

    /**
     * Months (up to this month) of the monthly salary / subscription that are not added yet.
     * الشهر بيطلع من أول يوم فيه، والشهر الجزئي حسب أيام الشهر الفعلية (قرار 10/11/2026).
     *
     * @return list<string>
     */
    public function pendingMonths(?string $until = null): array
    {
        if (! $this->hasMonthly()) {
            return [];
        }
        $recorded = $this->charges()->whereNotNull('period')->pluck('period')->all();
        // الشهور قبل تشرين الثاني 2026 انسجّلت بالإيد قبل هالميزة، فما بنعدّها «فاتتك».
        $from = $this->salary_from->toDateString() < self::MONTHLY_TRACKED_FROM
            ? \Carbon\CarbonImmutable::parse(self::MONTHLY_TRACKED_FROM) : $this->salary_from;

        return \App\Support\MonthlyPlan::pending($from, $this->salary_to, $recorded, $until ?? now()->format('Y-m'));
    }

    public function monthlySuggested(string $period): string
    {
        return \App\Support\MonthlyPlan::suggested($this->salary_amount, $this->salary_from, $this->salary_to, $period);
    }

    public function monthlyTitle(string $period): string
    {
        return $this->monthlyNoun().' شهر '.\App\Support\MonthlyPlan::label($period)
            .\App\Support\MonthlyPlan::daysNote($this->salary_from, $this->salary_to, $period);
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
