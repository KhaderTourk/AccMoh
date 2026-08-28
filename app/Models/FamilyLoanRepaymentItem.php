<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyLoanRepaymentItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'family_loan_repayment_id',
        'family_loan_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return ['allocated_amount' => 'decimal:2'];
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(FamilyLoanRepayment::class, 'family_loan_repayment_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(FamilyLoan::class, 'family_loan_id');
    }
}
