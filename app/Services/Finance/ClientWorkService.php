<?php

namespace App\Services\Finance;

use App\Enums\ClientServiceStatus;
use App\Exceptions\FinanceException;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\FinancialAuditLog;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ClientWorkService
{
    public function create(array $data): ClientService
    {
        $resolved = $this->resolvePricing($data);

        return DB::transaction(function () use ($data, $resolved) {
            $service = ClientService::query()->create([
                'client_id' => $data['client_id'],
                'service_type_id' => $data['service_type_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $resolved['amount'],
                'source_amount' => $resolved['source_amount'],
                'exchange_rate' => $resolved['exchange_rate'],
                'fx_currency_id' => $resolved['fx_currency_id'],
                'currency_id' => $data['currency_id'],
                'service_date' => $data['service_date'],
                'status' => $data['status'] ?? ClientServiceStatus::Completed,
                'notes' => $data['notes'] ?? null,
            ]);

            FinancialAuditLog::record('created', $service, ['amount' => $resolved['amount']]);

            return $service;
        });
    }

    public function update(ClientService $service, array $data): ClientService
    {
        $resolved = $this->resolvePricing($data);

        $service->update([
            'service_type_id' => $data['service_type_id'] ?? $service->service_type_id,
            'title' => $data['title'],
            'description' => array_key_exists('description', $data) ? $data['description'] : $service->description,
            'amount' => $resolved['amount'],
            'source_amount' => $resolved['source_amount'],
            'exchange_rate' => $resolved['exchange_rate'],
            'fx_currency_id' => $resolved['fx_currency_id'],
            'currency_id' => $data['currency_id'],
            'service_date' => $data['service_date'],
            'status' => $data['status'] ?? $service->status,
            'notes' => $data['notes'] ?? null,
        ]);

        FinancialAuditLog::record('updated', $service);

        return $service->refresh();
    }

    public function delete(ClientService $service): void
    {
        $service->delete();
        FinancialAuditLog::record('deleted', $service);
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
                throw new FinanceException('سعر الخدمة بالدولار يجب أن يكون أكبر من صفر.');
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
            throw new FinanceException('سعر الخدمة يجب أن يكون أكبر من صفر.');
        }

        return [
            'amount' => $amount,
            'source_amount' => null,
            'exchange_rate' => null,
            'fx_currency_id' => null,
        ];
    }
}
