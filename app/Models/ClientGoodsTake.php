<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasIlsExchange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientGoodsTake extends Model
{
    use BelongsToTenant;
    use HasIlsExchange;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'title',
        'amount',
        'source_amount',
        'exchange_rate',
        'fx_currency_id',
        'currency_id',
        'taken_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'source_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'taken_on' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
