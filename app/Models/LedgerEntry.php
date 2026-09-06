<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use App\Enums\TransactionType;
use App\Models\CashPayment;
use App\Models\FundTransfer;
use App\Models\Vendor;
use App\Models\VendorCharge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'group_id',
        'transaction_type',
        'fund_id',
        'payment_method_id',
        'currency_id',
        'amount',
        'occurred_on',
        'description',
        'notes',
        'related_type',
        'related_id',
        'created_by',
        'is_reversal',
        'reverses_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
            'transaction_type' => TransactionType::class,
            'is_reversal' => 'boolean',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function recordedAt(): string
    {
        return $this->created_at?->format('H:i') ?? '—';
    }

    public function counterpartyLabel(): ?string
    {
        $related = $this->related;
        if ($related instanceof CashPayment) {
            $party = $related->party;
            if ($party) {
                $kind = match ($related->party_type) {
                    'client' => 'زبون',
                    'person' => 'شخص',
                    'vendor' => $party instanceof Vendor ? $party->type->label() : 'طرف',
                    default => null,
                };

                return trim(($kind ? $kind.' · ' : '').($party->name ?? $related->name));
            }

            return $related->name;
        }

        if ($related instanceof FundTransfer) {
            return $related->fund?->name ?: 'تحويل';
        }

        if ($related instanceof VendorCharge) {
            return $related->vendor?->name;
        }

        return null;
    }
}
