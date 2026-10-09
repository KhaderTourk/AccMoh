<?php

namespace App\Models;

use App\Enums\BalanceMemoDirection;
use App\Enums\BalanceMemoLeg;
use App\Enums\BalanceMemoReason;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartyBalanceMemo extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'party_type',
        'party_id',
        'direction',
        'reason',
        'leg',
        'group_id',
        'amount',
        'currency_id',
        'memo_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => BalanceMemoDirection::class,
            'reason' => BalanceMemoReason::class,
            'leg' => BalanceMemoLeg::class,
            'amount' => 'decimal:2',
            'memo_date' => 'date',
        ];
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public static function signedAmountSql(): string
    {
        return "(CASE
            WHEN leg = 'source' AND direction = 'owed_to_us' THEN -amount
            WHEN leg = 'source' AND direction = 'owed_by_us' THEN amount
            WHEN direction = 'owed_to_us' THEN amount
            ELSE -amount
        END)";
    }

    /**
     * Positive means this memo increases what the party owes us (عليه).
     */
    public function owedToUsDelta(): string
    {
        $signed = $this->direction === BalanceMemoDirection::OwedToUs
            ? Money::of($this->amount)
            : Money::neg($this->amount);

        if ($this->leg === BalanceMemoLeg::Source) {
            $signed = Money::neg($signed);
        }

        return $signed;
    }

    public function isTransfer(): bool
    {
        return $this->reason === BalanceMemoReason::AccountTransfer && filled($this->group_id);
    }

    public function counterpart(): ?self
    {
        if (! $this->group_id) {
            return null;
        }

        return static::query()
            ->where('group_id', $this->group_id)
            ->whereKeyNot($this->id)
            ->first();
    }

    public function statementTitle(): string
    {
        $title = $this->reason->label();
        $other = $this->isTransfer() ? $this->counterpart() : null;
        if ($other?->party) {
            $name = $other->party instanceof Client ? $other->party->directoryLabel() : $other->party->name;
            $title .= $this->leg === BalanceMemoLeg::Source ? ' إلى '.$name : ' من '.$name;
        }

        return $title;
    }
}
