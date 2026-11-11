<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\MonthlyPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A monthly service for one client. Each calendar month is confirmed by hand and then
 * recorded as a ClientService with subscription_id + period («Y-m»).
 */
class ClientSubscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'client_id', 'service_type_id', 'title', 'amount', 'first_amount', 'currency_id', 'starts_on', 'ends_on', 'notes',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function months(): HasMany
    {
        return $this->hasMany(ClientService::class, 'subscription_id');
    }

    public function isActiveOn(CarbonImmutable $day): bool
    {
        return $this->starts_on->toDateString() <= $day->toDateString()
            && (! $this->ends_on || $this->ends_on->toDateString() >= $day->toDateString());
    }

    /** Whether any day of the month («Y-m») falls inside the subscription. */
    public function coversMonth(string $period): bool
    {
        return MonthlyPlan::covers($this->starts_on, $this->ends_on, $period);
    }

    /**
     * أيام الشغل بالشهر («Y-m») وأيام الشهر الفعلية (قرار 10/11/2026، بدل 30 ثابت).
     *
     * @return array{0: int, 1: int}
     */
    public function daysIn(string $period): array
    {
        return MonthlyPlan::daysIn($this->starts_on, $this->ends_on, $period);
    }

    /** المبلغ المقترح للشهر = السعر ÷ أيام الشهر × أيام الشغل، بالكسور، وقابل للتعديل. */
    public function suggestedAmount(string $period): string
    {
        return VendorSubscription::firstOr($this, $period)
            ?? MonthlyPlan::suggested($this->amount, $this->starts_on, $this->ends_on, $period);
    }

    /** الشهر بيطلع للتسجيل من أول يوم فيه، وبيضل ظاهر طول الشهر (قرار 10/11/2026). */
    public function isBillable(string $period, ?CarbonImmutable $today = null): bool
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        return $this->coversMonth($period) && $period <= $today->format('Y-m');
    }

    /** عنوان فاتورة الشهر، ومعه «20 يوم من 30» للشهر الجزئي. */
    public function monthTitle(string $period): string
    {
        return $this->title.' · شهر '.self::periodLabel($period).MonthlyPlan::daysNote($this->starts_on, $this->ends_on, $period);
    }

    /** ما في شهر «بيتراكم»: الشهر بيطلع للتسجيل من أوله. */
    public function accruing(?CarbonImmutable $today = null): ?array
    {
        return null;
    }

    /** خدمة الشهر بتنزل على الكشف بتاريخ أول يوم بالشهر. */
    public function serviceDateFor(string $period): string
    {
        return $period.'-01';
    }

    /**
     * Months from the start up to $until («Y-m») that are not recorded yet.
     *
     * @return list<string>
     */
    public function pendingMonths(string $until): array
    {
        $recorded = $this->relationLoaded('months')
            ? $this->months->pluck('period')->all()
            : $this->months()->pluck('period')->all();

        return MonthlyPlan::pending($this->starts_on, $this->ends_on, $recorded, $until);
    }

    public static function periodLabel(string $period): string
    {
        return MonthlyPlan::label($period);
    }
}
