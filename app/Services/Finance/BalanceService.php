<?php

namespace App\Services\Finance;

use App\Exceptions\FinanceException;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Support\Collection;

class BalanceService
{
    public function cash(int $fundId, int $paymentMethodId, int $currencyId): string
    {
        return Money::of(
            LedgerEntry::query()
                ->where('fund_id', $fundId)
                ->where('payment_method_id', $paymentMethodId)
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
    }

    public function fundCash(int $fundId, int $currencyId): string
    {
        return Money::of(
            LedgerEntry::query()
                ->where('fund_id', $fundId)
                ->where('currency_id', $currencyId)
                ->sum('amount')
        );
    }

    public function totalCash(int $currencyId): string
    {
        return Money::of(
            LedgerEntry::query()->where('currency_id', $currencyId)->sum('amount')
        );
    }

    public function assertSufficient(int $fundId, int $paymentMethodId, int $currencyId, mixed $amount, string $message = null): void
    {
        $needed = Money::of($amount);
        $available = $this->cash($fundId, $paymentMethodId, $currencyId);

        if (Money::cmp($available, $needed) < 0) {
            $currency = Currency::query()->find($currencyId);
            throw new FinanceException(
                $message ?: sprintf(
                    'الرصيد غير كافٍ. المتاح: %s — المطلوب: %s',
                    $currency?->format($available) ?? $available,
                    $currency?->format($needed) ?? $needed
                )
            );
        }
    }

    /**
     * @return array{currencies: Collection, methods: Collection, funds: Collection, cells: array, fund_totals: array, method_totals: array, grand: array}
     */
    public function snapshot(): array
    {
        $currencies = Currency::query()->active()->get();
        $methods = PaymentMethod::query()->active()->get();
        $funds = Fund::query()
            ->when(! tenantBusinessEnabled(), fn ($q) => $q->where('slug', '!=', 'business'))
            ->orderBy('id')
            ->get();

        $rows = LedgerEntry::query()
            ->selectRaw('fund_id, payment_method_id, currency_id, SUM(amount) as total')
            ->groupBy('fund_id', 'payment_method_id', 'currency_id')
            ->get();

        $cells = [];
        foreach ($rows as $row) {
            $cells[$row->fund_id][$row->payment_method_id][$row->currency_id] = Money::of($row->total);
        }

        $fundTotals = [];
        $methodTotals = [];
        $grand = [];

        foreach ($currencies as $currency) {
            $grand[$currency->id] = '0.00';
            foreach ($funds as $fund) {
                $fundTotals[$fund->id][$currency->id] = '0.00';
            }
            foreach ($methods as $method) {
                $methodTotals[$method->id][$currency->id] = '0.00';
            }
        }

        foreach ($cells as $fundId => $byMethod) {
            foreach ($byMethod as $methodId => $byCurrency) {
                foreach ($byCurrency as $currencyId => $amount) {
                    $fundTotals[$fundId][$currencyId] = Money::add($fundTotals[$fundId][$currencyId] ?? '0', $amount);
                    $methodTotals[$methodId][$currencyId] = Money::add($methodTotals[$methodId][$currencyId] ?? '0', $amount);
                    $grand[$currencyId] = Money::add($grand[$currencyId] ?? '0', $amount);
                }
            }
        }

        return compact('currencies', 'methods', 'funds', 'cells', 'fundTotals', 'methodTotals', 'grand');
    }

    /**
     * @return array<int, string> currency_id => amount
     */
    public function clientReceivables(?int $clientId = null): array
    {
        $billed = ClientService::query()
            ->billable()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->selectRaw('currency_id, SUM(amount) as total')
            ->groupBy('currency_id')
            ->pluck('total', 'currency_id');

        $paid = CashPayment::query()
            ->incoming()
            ->active()
            ->when($clientId, fn ($q) => $q->where('party_type', 'client')->where('party_id', $clientId))
            ->when(! $clientId, fn ($q) => $q->where('party_type', 'client'))
            ->selectRaw('currency_id, SUM(amount) as total')
            ->groupBy('currency_id')
            ->pluck('total', 'currency_id');

        $out = [];
        foreach (Currency::query()->active()->pluck('id') as $currencyId) {
            $out[$currencyId] = Money::sub($billed[$currencyId] ?? '0', $paid[$currencyId] ?? '0');
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public function personNet(?int $personId = null): array
    {
        $incoming = CashPayment::query()
            ->incoming()
            ->active()
            ->where('party_type', 'person')
            ->when($personId, fn ($q) => $q->where('party_id', $personId))
            ->selectRaw('currency_id, SUM(amount) as total')
            ->groupBy('currency_id')
            ->pluck('total', 'currency_id');

        $outgoing = CashPayment::query()
            ->outgoing()
            ->active()
            ->where('party_type', 'person')
            ->when($personId, fn ($q) => $q->where('party_id', $personId))
            ->selectRaw('currency_id, SUM(amount) as total')
            ->groupBy('currency_id')
            ->pluck('total', 'currency_id');

        $out = [];
        foreach (Currency::query()->active()->pluck('id') as $currencyId) {
            $out[$currencyId] = Money::sub($incoming[$currencyId] ?? '0', $outgoing[$currencyId] ?? '0');
        }

        return $out;
    }

    public function topIndebtedClients(int $limit = 5): Collection
    {
        $clients = Client::query()->active()->orderBy('name')->get();
        $currencies = Currency::query()->active()->get();

        return $clients->map(function (Client $client) use ($currencies) {
            $byCurrency = [];
            $hasDebt = false;
            foreach ($currencies as $currency) {
                $amount = $client->outstandingAmount($currency->id);
                $byCurrency[$currency->id] = $amount;
                if (Money::isPositive($amount)) {
                    $hasDebt = true;
                }
            }

            return [
                'client' => $client,
                'by_currency' => $byCurrency,
                'has_debt' => $hasDebt,
            ];
        })->filter(fn ($row) => $row['has_debt'])->take($limit)->values();
    }

    public function topPayingClients(int $limit = 5): Collection
    {
        $rows = CashPayment::query()
            ->incoming()
            ->active()
            ->where('party_type', 'client')
            ->selectRaw('party_id as client_id, currency_id, SUM(amount) as total')
            ->groupBy('party_id', 'currency_id')
            ->get()
            ->groupBy('client_id');

        $clients = Client::query()->whereIn('id', $rows->keys())->get()->keyBy('id');
        $currencies = Currency::query()->active()->get()->keyBy('id');

        return $rows->map(function ($payments, $clientId) use ($clients, $currencies) {
            $byCurrency = [];
            foreach ($payments as $payment) {
                $byCurrency[$payment->currency_id] = Money::of($payment->total);
            }

            return [
                'client' => $clients[$clientId] ?? null,
                'by_currency' => $byCurrency,
            ];
        })->filter(fn ($row) => $row['client'])->take($limit)->values();
    }
}
