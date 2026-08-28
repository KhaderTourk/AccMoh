<?php

namespace App\Services\Finance;

use App\Enums\ClientServiceStatus;
use App\Exceptions\FinanceException;
use App\Models\ClientPayment;
use App\Models\ClientService;
use App\Models\FinancialAuditLog;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ClientWorkService
{
    public function create(array $data): ClientService
    {
        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('سعر الخدمة يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($data, $amount) {
            $service = ClientService::query()->create([
                'client_id' => $data['client_id'],
                'service_type_id' => $data['service_type_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'currency_id' => $data['currency_id'],
                'service_date' => $data['service_date'],
                'status' => $data['status'] ?? ClientServiceStatus::Pending,
                'notes' => $data['notes'] ?? null,
            ]);

            FinancialAuditLog::record('created', $service, ['amount' => $amount]);

            return $service;
        });
    }

    public function update(ClientService $service, array $data): ClientService
    {
        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('سعر الخدمة يجب أن يكون أكبر من صفر.');
        }

        $newStatus = $data['status'] ?? $service->status;
        $isCancelled = $newStatus === ClientServiceStatus::Cancelled
            || $newStatus === ClientServiceStatus::Cancelled->value;

        $newCurrencyId = (int) $data['currency_id'];
        $oldCurrencyId = (int) $service->currency_id;

        $this->assertBilledCoversPaid(
            (int) $service->client_id,
            $oldCurrencyId,
            $service->id,
            (! $isCancelled && $newCurrencyId === $oldCurrencyId) ? $amount : '0.00'
        );

        if ($newCurrencyId !== $oldCurrencyId && ! $isCancelled) {
            $this->assertBilledCoversPaid(
                (int) $service->client_id,
                $newCurrencyId,
                $service->id,
                $amount
            );
        }

        $service->update([
            'service_type_id' => $data['service_type_id'] ?? $service->service_type_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount' => $amount,
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
        $this->assertBilledCoversPaid(
            (int) $service->client_id,
            (int) $service->currency_id,
            $service->id,
            '0.00'
        );

        $service->delete();
        FinancialAuditLog::record('deleted', $service);
    }

    protected function assertBilledCoversPaid(int $clientId, int $currencyId, ?int $excludeServiceId, mixed $replacementAmount): void
    {
        $billed = Money::of(
            ClientService::query()
                ->billable()
                ->where('client_id', $clientId)
                ->where('currency_id', $currencyId)
                ->when($excludeServiceId, fn ($q) => $q->where('id', '!=', $excludeServiceId))
                ->sum('amount')
        );
        $billed = Money::add($billed, $replacementAmount ?? 0);
        $paid = Money::of(
            ClientPayment::query()
                ->active()
                ->where('client_id', $clientId)
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );

        if (Money::cmp($billed, $paid) < 0) {
            throw new FinanceException('لا يمكن أن يصبح إجمالي خدمات العميل أقل من مجموع دفعاته بهذه العملة.');
        }
    }
}
