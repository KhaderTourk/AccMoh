<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasIlsExchange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientPayment extends Model
{
    use BelongsToTenant;
    use HasIlsExchange;

    protected $fillable = [
        'client_id',
        'fund_id',
        'amount',
        'source_amount',
        'exchange_rate',
        'fx_currency_id',
        'currency_id',
        'payment_method_id',
        'payer_name',
        'payment_date',
        'notes',
        'ledger_group_id',
        'is_reversed',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'source_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'payment_date' => 'date',
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_reversed', false);
    }
}
