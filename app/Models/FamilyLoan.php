<?php

namespace App\Models;

use App\Enums\LoanDirection;
use App\Enums\LoanStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyLoan extends Model
{
    protected $fillable = [
        'family_member_id',
        'fund_id',
        'direction',
        'amount',
        'currency_id',
        'payment_method_id',
        'loan_date',
        'status',
        'notes',
        'ledger_group_id',
        'is_reversed',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'loan_date' => 'date',
            'direction' => LoanDirection::class,
            'status' => LoanStatus::class,
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

    public function repaymentItems(): HasMany
    {
        return $this->hasMany(FamilyLoanRepaymentItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_reversed', false);
    }

    public function repaidAmount(): string
    {
        return Money::of(
            $this->repaymentItems()
                ->whereHas('repayment', fn ($q) => $q->active())
                ->sum('allocated_amount')
        );
    }

    public function remainingAmount(): string
    {
        if ($this->is_reversed) {
            return '0.00';
        }

        return Money::sub($this->amount, $this->repaidAmount());
    }

    public function refreshStatus(): void
    {
        $remaining = $this->remainingAmount();
        $status = Money::isZero($remaining)
            ? LoanStatus::Paid
            : (Money::cmp($remaining, $this->amount) < 0 ? LoanStatus::Partial : LoanStatus::Open);

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }
}
