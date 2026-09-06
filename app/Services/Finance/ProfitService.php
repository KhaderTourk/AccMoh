<?php

namespace App\Services\Finance;

use App\Enums\PaymentDirection;
use App\Enums\VendorType;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Support\DateRange;
use App\Support\Money;

class ProfitService
{
    /**
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
        $from = DateRange::normalize($from);
        $to = DateRange::normalize($to);
        if ($from && $to && strcmp($from, $to) > 0) {
            [$from, $to] = [$to, $from];
        }

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
                CashPayment::query()
                    ->incoming()
                    ->active()
                    ->where('party_type', 'client')
                    ->where('currency_id', $currency->id)
                    ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to))
                    ->sum('amount')
            );

            $workQuery = CashPayment::query()
                ->outgoing()
                ->active()
                ->where('fund_id', $business->id)
                ->where('currency_id', $currency->id)
                ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to));

            $workExpenses = Money::of((clone $workQuery)->sum('amount'));
            $workerIds = \App\Models\Vendor::query()->ofType(VendorType::Worker)->pluck('id');
            $supplierIds = \App\Models\Vendor::query()->ofType(VendorType::Supplier)->pluck('id');
            $workerExpenses = Money::of(
                (clone $workQuery)->where('party_type', 'vendor')->whereIn('party_id', $workerIds)->sum('amount')
            );
            $supplierExpenses = Money::of(
                (clone $workQuery)->where('party_type', 'vendor')->whereIn('party_id', $supplierIds)->sum('amount')
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
