<?php

namespace App\Models;

use App\Enums\ClientServiceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasIlsExchange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientService extends Model
{
    use BelongsToTenant;
    use HasIlsExchange;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'service_type_id',
        'title',
        'description',
        'amount',
        'source_amount',
        'exchange_rate',
        'fx_currency_id',
        'currency_id',
        'service_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'source_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'service_date' => 'date',
            'status' => ClientServiceStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('status', '!=', ClientServiceStatus::Cancelled);
    }

    public function paidAmount(): string
    {
        return Money::of(
            $this->allocations()
                ->whereHas('payment', fn ($q) => $q->active())
                ->sum('allocated_amount')
        );
    }

    public function remainingAmount(): string
    {
        if ($this->status === ClientServiceStatus::Cancelled) {
            return '0.00';
        }

        return Money::sub($this->amount, $this->paidAmount());
    }

    public function isFullyPaid(): bool
    {
        return Money::cmp($this->remainingAmount(), '0') <= 0;
    }
}
