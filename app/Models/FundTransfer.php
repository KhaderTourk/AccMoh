<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransfer extends Model
{
    protected $fillable = [
        'fund_id',
        'from_payment_method_id',
        'to_payment_method_id',
        'amount',
        'currency_id',
        'fee_amount',
        'transfer_date',
        'notes',
        'ledger_group_id',
        'is_reversed',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'transfer_date' => 'date',
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function fromMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'from_payment_method_id');
    }

    public function toMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'to_payment_method_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_reversed', false);
    }
}
