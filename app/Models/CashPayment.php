<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasIlsExchange;
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

    public function scopeIncoming(Builder $query): Builder
    {
        return $query->where('direction', PaymentDirection::Incoming);
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
