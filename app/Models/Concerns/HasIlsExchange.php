<?php

namespace App\Models\Concerns;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasIlsExchange
{
    public function fxCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'fx_currency_id');
    }

    public function isFx(): bool
    {
        return $this->source_amount !== null && $this->exchange_rate !== null;
    }

    public function formattedExchangeRate(): ?string
    {
        if ($this->exchange_rate === null) {
            return null;
        }

        $raw = trim((string) $this->exchange_rate);
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '.')) {
            $raw = rtrim(rtrim($raw, '0'), '.');
        }

        return $raw === '' ? '0' : $raw;
    }
}
