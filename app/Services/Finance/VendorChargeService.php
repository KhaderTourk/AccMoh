<?php

namespace App\Services\Finance;

use App\Exceptions\FinanceException;
use App\Models\Currency;
use App\Models\FinancialAuditLog;
use App\Models\VendorCharge;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class VendorChargeService
{
    public function create(array $data): VendorCharge
    {
        $resolved = $this->resolvePricing($data);

        return DB::transaction(function () use ($data, $resolved) {
            $charge = VendorCharge::query()->create([
                'vendor_id' => $data['vendor_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $resolved['amount'],
                'source_amount' => $resolved['source_amount'],
                'exchange_rate' => $resolved['exchange_rate'],
                'fx_currency_id' => $resolved['fx_currency_id'],
                'currency_id' => $data['currency_id'],
                'charge_date' => $data['charge_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            FinancialAuditLog::record('created', $charge, ['amount' => $resolved['amount']]);

            return $charge;
        });
    }

    public function update(VendorCharge $charge, array $data): VendorCharge
    {
        $resolved = $this->resolvePricing($data);

        $charge->update([
            'title' => $data['title'],
            'description' => array_key_exists('description', $data) ? $data['description'] : $charge->description,
            'amount' => $resolved['amount'],
            'source_amount' => $resolved['source_amount'],
            'exchange_rate' => $resolved['exchange_rate'],
            'fx_currency_id' => $resolved['fx_currency_id'],
            'currency_id' => $data['currency_id'],
            'charge_date' => $data['charge_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        FinancialAuditLog::record('updated', $charge);

        return $charge->refresh();
    }

    public function delete(VendorCharge $charge): void
    {
        $charge->delete();
        FinancialAuditLog::record('deleted', $charge);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{amount: string, source_amount: string|null, exchange_rate: mixed, fx_currency_id: int|null}
     */
    protected function resolvePricing(array $data): array
    {
        $hasFx = filled($data['source_amount'] ?? null) && filled($data['exchange_rate'] ?? null);

        if ($hasFx) {
            $source = $data['source_amount'];
            $rate = $data['exchange_rate'];
            if (! is_numeric($source) || ! Money::isPositive($source)) {
                throw new FinanceException('السعر بالدولار يجب أن يكون أكبر من صفر.');
            }
            if (! is_numeric($rate) || (float) $rate <= 0) {
                throw new FinanceException('سعر الدولار يجب أن يكون أكبر من صفر.');
            }

            $amount = Money::mul($source, $rate);
            if (! Money::isPositive($amount)) {
                throw new FinanceException('القيمة الإجمالية بالشيكل يجب أن تكون أكبر من صفر.');
            }

            return [
                'amount' => $amount,
                'source_amount' => Money::of($source),
                'exchange_rate' => $rate,
                'fx_currency_id' => $data['fx_currency_id'] ?? Currency::query()->where('code', 'USD')->value('id'),
            ];
        }

        $amount = Money::of($data['amount'] ?? 0);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('السعر يجب أن يكون أكبر من صفر.');
        }

        return [
            'amount' => $amount,
            'source_amount' => null,
            'exchange_rate' => null,
            'fx_currency_id' => null,
        ];
    }
}
