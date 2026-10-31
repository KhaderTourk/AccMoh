<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasIlsExchange;
use App\Support\ArchiveVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashPayment extends Model
{
    use BelongsToTenant;
    use HasIlsExchange;

    protected $fillable = [
        'tenant_id',
        'direction',
        'kind',
        'fund_id',
        'payment_method_id',
        'currency_id',
        'amount',
        'source_amount',
        'exchange_rate',
        'fx_currency_id',
        'name',
        'account_holder_name',
        'party_type',
        'party_id',
        'occurred_on',
        'notes',
        'ledger_group_id',
        'is_reversed',
        'reversed_at',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'amount' => 'decimal:2',
            'source_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'occurred_on' => 'date',
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_reversed', false);
    }

    /**
     * Hide movements that belong to an archived client, person, employee, or supplier.
     */
    public function scopeWithoutArchivedParties(Builder $query): Builder
    {
        if (ArchiveVisibility::showing()) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $outer) use ($table) {
            $outer->whereNull("{$table}.party_type")
                ->orWhere(function (Builder $inner) use ($table) {
                    $inner->whereIn("{$table}.party_type", ['client', Client::class])
                        ->whereIn("{$table}.party_id", Client::query()->where('is_active', true)->select('id'));
                })
                ->orWhere(function (Builder $inner) use ($table) {
                    $inner->whereIn("{$table}.party_type", ['person', Person::class])
                        ->whereIn("{$table}.party_id", Person::query()->where('is_active', true)->select('id'));
                })
                ->orWhere(function (Builder $inner) use ($table) {
                    $inner->whereIn("{$table}.party_type", ['vendor', Vendor::class])
                        ->whereIn("{$table}.party_id", Vendor::query()->where('is_active', true)->select('id'));
                })
                ->orWhere(function (Builder $inner) use ($table) {
                    $inner->whereNotNull("{$table}.party_type")
                        ->whereNotIn("{$table}.party_type", [
                            'client', Client::class,
                            'person', Person::class,
                            'vendor', Vendor::class,
                        ]);
                });
        });
    }

    public function scopeIncoming(Builder $query): Builder
    {
        return $query->where('direction', PaymentDirection::Incoming);
    }

    /** Client receipts: incoming payments plus refunds paid back (summed with clientReceiptSql()). */
    public function scopeClientReceipts(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('direction', PaymentDirection::Incoming)
            ->orWhere(fn ($r) => $r->where('direction', PaymentDirection::Outgoing)->where('kind', 'refund')));
    }

    public static function clientReceiptSql(): string
    {
        return "CASE WHEN direction = 'outgoing' THEN -amount ELSE amount END";
    }

    public function scopeRefunds(Builder $query): Builder
    {
        return $query->where('direction', PaymentDirection::Outgoing)->where('kind', 'refund');
    }

    public function scopeOutgoing(Builder $query): Builder
    {
        return $query->where('direction', PaymentDirection::Outgoing);
    }

    public function isIncoming(): bool
    {
        return $this->direction === PaymentDirection::Incoming;
    }
}
