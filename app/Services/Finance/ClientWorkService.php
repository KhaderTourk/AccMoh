<?php

namespace App\Services\Finance;

use App\Enums\ClientServiceStatus;
use App\Exceptions\FinanceException;
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
        if ($service->allocations()->whereHas('payment', fn ($q) => $q->active())->exists()) {
            if (
                (int) $data['currency_id'] !== (int) $service->currency_id
                || Money::cmp($data['amount'], $service->amount) < 0
                    && Money::cmp($data['amount'], $service->paidAmount()) < 0
            ) {
                throw new FinanceException('لا يمكن تقليل سعر الخدمة أو تغيير عملتها بعد وجود دفعات مخصصة.');
            }
            if (Money::cmp($data['amount'], $service->paidAmount()) < 0) {
                throw new FinanceException('سعر الخدمة لا يجوز أن يكون أقل من المبلغ المدفوع عليها.');
            }
        }

        $amount = Money::of($data['amount']);
        if (! Money::isPositive($amount)) {
            throw new FinanceException('سعر الخدمة يجب أن يكون أكبر من صفر.');
        }

        if (($data['status'] ?? null) === ClientServiceStatus::Cancelled->value
            || ($data['status'] ?? null) === ClientServiceStatus::Cancelled) {
            if (Money::isPositive($service->paidAmount())) {
                throw new FinanceException('لا يمكن إلغاء خدمة عليها دفعات. ألغِ الدفعات أولاً أو أبقِ الخدمة.');
            }
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
        if ($service->allocations()->whereHas('payment', fn ($q) => $q->active())->exists()) {
            throw new FinanceException('لا يمكن حذف خدمة مرتبطة بدفعات. استخدم الإلغاء إن لم تُدفع، أو ألغِ الدفعات أولاً.');
        }

        $service->delete();
        FinancialAuditLog::record('deleted', $service);
    }
}
