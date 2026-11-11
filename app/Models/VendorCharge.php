<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasIlsExchange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorCharge extends Model
{
    use BelongsToTenant;
    use HasIlsExchange;
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'charge_type_id',
        'subscription_id',
        'title',
        'description',
        'amount',
        'source_amount',
        'exchange_rate',
        'fx_currency_id',
        'currency_id',
        'charge_date',
        'period',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'source_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'charge_date' => 'date',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(VendorChargeType::class, 'charge_type_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(VendorSubscription::class, 'subscription_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
