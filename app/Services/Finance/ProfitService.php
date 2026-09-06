<?php

namespace App\Services\Finance;

use App\Enums\PaymentDirection;
use App\Enums\VendorType;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\Vendor;
use App\Models\VendorCharge;
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
     *     client_outstanding: string,
     *     worker_outstanding: string,
     *     supplier_outstanding: string,
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

            $clientOutstanding = '0.00';
            foreach (Client::query()->get() as $client) {
                $due = $client->outstandingAmount($currency->id);
                if (Money::isPositive($due)) {
                    $clientOutstanding = Money::add($clientOutstanding, $due);
                }
            }

            $workerOutstanding = $this->vendorRemaining(VendorType::Worker, $currency->id);
            $supplierOutstanding = $this->vendorRemaining(VendorType::Supplier, $currency->id);
            $vendorOutstanding = Money::add($workerOutstanding, $supplierOutstanding);

            if (
                Money::isZero($payments)
                && Money::isZero($workExpenses)
                && Money::isZero($clientOutstanding)
                && Money::isZero($vendorOutstanding)
            ) {
                continue;
            }

            $rows[] = [
                'currency' => $currency,
                'payments' => $payments,
                'work_expenses' => $workExpenses,
                'worker_expenses' => $workerExpenses,
                'supplier_expenses' => $supplierExpenses,
                'client_outstanding' => $clientOutstanding,
                'worker_outstanding' => $workerOutstanding,
                'supplier_outstanding' => $supplierOutstanding,
                'outstanding' => $vendorOutstanding,
                'net_profit' => Money::sub($payments, $workExpenses),
                'gross_profit' => Money::sub(Money::add($vendorOutstanding, $payments), $workExpenses),
            ];
        }

        return $rows;
    }

    protected function vendorRemaining(VendorType $type, int $currencyId): string
    {
        $ids = Vendor::query()->ofType($type)->pluck('id');
        if ($ids->isEmpty()) {
            return '0.00';
        }

        $billed = Money::of(
            VendorCharge::query()
                ->whereIn('vendor_id', $ids)
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
        $paid = Money::of(
            CashPayment::query()
                ->outgoing()
                ->active()
                ->where('party_type', 'vendor')
                ->whereIn('party_id', $ids)
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
        $due = Money::sub($billed, $paid);

        return Money::isPositive($due) ? $due : '0.00';
    }
}
