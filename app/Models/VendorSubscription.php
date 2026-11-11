<?php

namespace App\Models;

use App\Enums\VendorType;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use App\Support\MonthlyPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * راتب شهري (موظف) أو اشتراك شهري (مورد) على نوع مستحق، وأكثر من واحد لكل حساب (قرار 11/11/2026).
 * ولا شهر بينضاف لحاله: كل شهر بزر «أضف»، وبينسجّل VendorCharge عليه subscription_id + period.
 */
class VendorSubscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'vendor_id', 'charge_type_id', 'title', 'amount', 'first_amount', 'currency_id', 'starts_on', 'ends_on', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'first_amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(VendorChargeType::class, 'charge_type_id');
    }

    public function months(): HasMany
    {
        return $this->hasMany(VendorCharge::class, 'subscription_id');
    }

    public function noun(): string
    {
        return $this->vendor?->type === VendorType::Worker ? 'الراتب' : 'الاشتراك';
    }

    public function coversMonth(string $period): bool
    {
        return MonthlyPlan::covers($this->starts_on, $this->ends_on, $period);
    }

    /** @return array{0: int, 1: int} */
    public function daysIn(string $period): array
    {
        return MonthlyPlan::daysIn($this->starts_on, $this->ends_on, $period);
    }

    /** المقترح = الاتفاق ÷ أيام الشهر × أيام الشغل؛ وأول شهر بالمبلغ اللي انكتب إذا انعدّل. */
    public function suggestedAmount(string $period): string
    {
        return self::firstOr($this, $period) ?? MonthlyPlan::suggested($this->amount, $this->starts_on, $this->ends_on, $period);
    }

    /** مبلغ أول شهر المعدّل، إذا الشهر هو أول شهر والاشتراك ما انوقف بنفس الشهر. */
    public static function firstOr(Model $sub, string $period): ?string
    {
        if ($sub->first_amount === null || $sub->starts_on->format('Y-m') !== $period) {
            return null;
        }
        if ($sub->ends_on && $sub->ends_on->format('Y-m') === $period) {
            return null;
        }

        return Money::of($sub->first_amount);
    }

    public function isBillable(string $period, ?CarbonImmutable $today = null): bool
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        return $this->coversMonth($period) && $period <= $today->format('Y-m');
    }

    public function monthTitle(string $period): string
    {
        return $this->title.' · شهر '.MonthlyPlan::label($period).MonthlyPlan::daysNote($this->starts_on, $this->ends_on, $period);
    }

    /**
     * الشهور لحد $until اللي لسا ما انضافت. الشهور قبل تشرين الثاني 2026 انسجّلت بالإيد قبل الميزة.
     *
     * @return list<string>
     */
    public function pendingMonths(string $until): array
    {
        $recorded = $this->relationLoaded('months')
            ? $this->months->pluck('period')->filter()->all()
            : $this->months()->whereNotNull('period')->pluck('period')->all();
        $from = $this->starts_on->toDateString() < Vendor::MONTHLY_TRACKED_FROM
            ? CarbonImmutable::parse(Vendor::MONTHLY_TRACKED_FROM) : $this->starts_on;

        return MonthlyPlan::pending($from, $this->ends_on, array_values($recorded), $until);
    }
}
