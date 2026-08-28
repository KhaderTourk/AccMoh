<?php

namespace App\Services\Finance;

use App\Enums\VendorType;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Fund;
use App\Support\Money;

class ProfitService
{
    /**
     * Period applies to client payments and work-fund expenses.
     * Outstanding receivables are the current remaining dues (positive only).
     *
     * @return list<array{
     *     currency: Currency,
     *     payments: string,
     *     work_expenses: string,
     *     worker_expenses: string,
     *     supplier_expenses: string,
     *     outstanding: string,
     *     net_profit: string,
     *     gross_profit: string
     * }>
     */
    public function forPeriod(?string $from, ?string $to): array
    {
        if (! tenantBusinessEnabled()) {
            return [];
        }

        $business = Fund::query()->where('slug', 'business')->first();
        if (! $business) {
            return [];
        }

        $rows = [];
        foreach (Currency::query()->active()->get() as $currency) {
            $payments = Money::of(
                ClientPayment::query()
                    ->active()
                    ->where('currency_id', $currency->id)
                    ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
                    ->sum('amount')
            );

            $workQuery = Expense::query()
                ->active()
                ->where('fund_id', $business->id)
                ->where('currency_id', $currency->id)
                ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to));

            $workExpenses = Money::of((clone $workQuery)->sum('amount'));
            $workerExpenses = Money::of(
                (clone $workQuery)->whereHas('vendor', fn ($q) => $q->where('type', VendorType::Worker))->sum('amount')
            );
            $supplierExpenses = Money::of(
                (clone $workQuery)->whereHas('vendor', fn ($q) => $q->where('type', VendorType::Supplier))->sum('amount')
            );

            $outstanding = '0.00';
            foreach (Client::query()->get() as $client) {
                $due = $client->outstandingAmount($currency->id);
                if (Money::isPositive($due)) {
                    $outstanding = Money::add($outstanding, $due);
                }
            }

            if (
                Money::isZero($payments)
                && Money::isZero($workExpenses)
                && Money::isZero($outstanding)
            ) {
                continue;
            }

            $rows[] = [
                'currency' => $currency,
                'payments' => $payments,
                'work_expenses' => $workExpenses,
                'worker_expenses' => $workerExpenses,
                'supplier_expenses' => $supplierExpenses,
                'outstanding' => $outstanding,
                'net_profit' => Money::sub($payments, $workExpenses),
                'gross_profit' => Money::sub(Money::add($outstanding, $payments), $workExpenses),
            ];
        }

        return $rows;
    }
}
