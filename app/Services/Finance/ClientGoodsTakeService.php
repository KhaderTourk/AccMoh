<?php

namespace App\Services\Finance;

use App\Exceptions\FinanceException;
use App\Models\ClientGoodsTake;
use App\Models\Currency;
use App\Models\FinancialAuditLog;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ClientGoodsTakeService
{
    public function create(array $data): ClientGoodsTake
    {
        $resolved = $this->resolvePricing($data);

        return DB::transaction(function () use ($data, $resolved) {
            $row = ClientGoodsTake::query()->create([
                'client_id' => $data['client_id'],
                'title' => $data['title'],
                'amount' => $resolved['amount'],
                'source_amount' => $resolved['source_amount'],
                'exchange_rate' => $resolved['exchange_rate'],
                'fx_currency_id' => $resolved['fx_currency_id'],
                'currency_id' => $data['currency_id'],
                'taken_on' => $data['taken_on'],
                'notes' => $data['notes'] ?? null,
            ]);

            FinancialAuditLog::record('created', $row, ['amount' => $resolved['amount']]);

            return $row;
        });
    }

    public function update(ClientGoodsTake $row, array $data): ClientGoodsTake
    {
        $resolved = $this->resolvePricing($data);

        $row->update([
            'title' => $data['title'],
            'amount' => $resolved['amount'],
            'source_amount' => $resolved['source_amount'],
            'exchange_rate' => $resolved['exchange_rate'],
            'fx_currency_id' => $resolved['fx_currency_id'],
            'currency_id' => $data['currency_id'],
            'taken_on' => $data['taken_on'],
            'notes' => $data['notes'] ?? null,
        ]);

        FinancialAuditLog::record('updated', $row);

        return $row->refresh();
    }

    public function delete(ClientGoodsTake $row): void
    {
        $row->delete();
        FinancialAuditLog::record('deleted', $row);
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
