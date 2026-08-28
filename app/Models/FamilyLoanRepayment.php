<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use App\Enums\LoanDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyLoanRepayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'family_member_id',
        'fund_id',
        'direction',
        'amount',
        'currency_id',
        'payment_method_id',
        'repayment_date',
        'notes',
        'ledger_group_id',
        'is_reversed',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'repayment_date' => 'date',
            'direction' => LoanDirection::class,
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(FamilyLoanRepaymentItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_reversed', false);
    }
}
