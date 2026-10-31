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

    /** Suggested amount for a month: full price, or by days for the first and last month. */
    public function suggestedAmount(string $period): string
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $end = $start->endOfMonth()->startOfDay();
        $from = $this->starts_on->greaterThan($start) ? CarbonImmutable::parse($this->starts_on) : $start;
        $to = $this->ends_on && $this->ends_on->lessThan($end) ? CarbonImmutable::parse($this->ends_on) : $end;
        if ($to->lessThan($from)) {
            return '0.00';
        }
        $days = $from->diffInDays($to) + 1;
        $inMonth = $start->daysInMonth;
        if ($days >= $inMonth) {
            return Money::of($this->amount);
        }

        return Money::of(round((float) $this->amount * $days / $inMonth, 2));
    }

    /** First day of the month that is still inside the subscription (service date for that month). */
    public function serviceDateFor(string $period): string
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01');

        return $this->starts_on->greaterThan($start) ? $this->starts_on->toDateString() : $start->toDateString();
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
