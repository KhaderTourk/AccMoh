<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
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
        'client_id', 'service_type_id', 'title', 'amount', 'currency_id', 'starts_on', 'ends_on', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $end = $start->endOfMonth()->startOfDay();

        return $this->starts_on->toDateString() <= $end->toDateString()
            && (! $this->ends_on || $this->ends_on->toDateString() >= $start->toDateString());
    }

    /**
     * أيام الشهر («Y-m») داخل الاشتراك، والشهر دايماً 30 يوم (قرار 1/11/2026):
     * بلّش بنصه = 30 − يوم البداية + 1، ووقف بنصه = يوم الإيقاف، ويوم 31 بينحسب 30، وشباط 30.
     *
     * @return array{0: int, 1: int}
     */
    public function daysIn(string $period): array
    {
        if (! $this->coversMonth($period)) {
            return [0, 30];
        }
        $from = $this->starts_on->format('Y-m') === $period ? min(30, (int) $this->starts_on->format('j')) : 1;
        $to = $this->ends_on && $this->ends_on->format('Y-m') === $period ? min(30, (int) $this->ends_on->format('j')) : 30;
        // شباط: إذا وقف آخر يوم بالشهر بينحسب شهر كامل
        if ($this->ends_on && $this->ends_on->format('Y-m') === $period && $this->ends_on->isLastOfMonth()) {
            $to = 30;
        }

        return [max(0, $to - $from + 1), 30];
    }

    /** المبلغ المقترح للشهر = السعر × الأيام ÷ 30، وقابل للتعديل. */
    public function suggestedAmount(string $period): string
    {
        [$days, $inMonth] = $this->daysIn($period);
        if ($days <= 0) {
            return '0.00';
        }
        if ($days >= $inMonth) {
            return Money::of($this->amount);
        }

        return Money::of(round((float) $this->amount * $days / $inMonth, 2));
    }

    /**
     * فاتورة الشهر بتطلع من أوله، والشهر الجزئي كمان (أول شهر بينسجل فوراً عند الإضافة).
     */
    public function isBillable(string $period, ?CarbonImmutable $today = null): bool
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        return $this->coversMonth($period) && $period <= $today->format('Y-m');
    }

    /** عنوان فاتورة الشهر، ومعه «21 يوم من 30» للشهر الجزئي. */
    public function monthTitle(string $period): string
    {
        [$days, $inMonth] = $this->daysIn($period);

        return $this->title.' · شهر '.self::periodLabel($period).($days < $inMonth ? " ({$days} يوم من {$inMonth})" : '');
    }

    /** ما في شهر «بيتراكم» بعد قرار 1/11: الشهر الجزئي بينسجل من أوله. */
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
        $out = [];
        $cursor = CarbonImmutable::parse($this->starts_on)->startOfMonth();
        $last = CarbonImmutable::createFromFormat('Y-m-d', $until.'-01');
        if ($this->ends_on && $this->ends_on->lessThan($last)) {
            $last = CarbonImmutable::parse($this->ends_on)->startOfMonth();
        }
        while ($cursor->lessThanOrEqualTo($last)) {
            $p = $cursor->format('Y-m');
            if (! in_array($p, $recorded, true)) {
                $out[] = $p;
            }
            $cursor = $cursor->addMonth();
        }

        return $out;
    }

    public static function periodLabel(string $period): string
    {
        [$y, $m] = explode('-', $period);

        return ((int) $m).'/'.$y;
    }
}
