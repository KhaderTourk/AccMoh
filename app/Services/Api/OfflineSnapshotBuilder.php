<?php

namespace App\Services\Api;

use App\Models\CashPayment;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\ServiceType;
use App\Models\Vendor;
use App\Services\Finance\BalanceService;
use App\Support\Money;

class OfflineSnapshotBuilder
{
    public function __construct(protected BalanceService $balances) {}

    public function build(): array
    {
        $snapshot = $this->balances->snapshot();

        return [
            'server_time' => now()->toIso8601String(),
            'catalog' => [
                'currencies' => Currency::query()->active()->get(['id', 'code', 'name', 'symbol']),
                'payment_methods' => PaymentMethod::query()->active()->get(['id', 'name', 'slug', 'icon']),
                'funds' => Fund::query()
                    ->when(! tenantBusinessEnabled(), fn ($q) => $q->where('slug', '!=', 'business'))
                    ->orderBy('id')
                    ->get(['id', 'name', 'slug']),
                'service_types' => tenantBusinessEnabled()
                    ? ServiceType::query()->active()->get(['id', 'name', 'description'])
                    : collect(),
            ],
            'clients' => tenantBusinessEnabled() ? $this->clients($snapshot) : [],
            'persons' => $this->persons($snapshot),
            'vendors' => tenantBusinessEnabled() ? $this->vendors() : [],
            'unpaid_services' => tenantBusinessEnabled() ? $this->unpaidServices() : [],
            'balances' => $this->formatBalances($snapshot),
            'receivables' => tenantBusinessEnabled()
                ? $this->mapByCurrencyCode($this->balances->clientReceivables(), $snapshot['currencies'])
                : $this->mapByCurrencyCode([], $snapshot['currencies']),
            'person_net' => $this->mapByCurrencyCode($this->balances->personNet(), $snapshot['currencies']),
            'features' => [
                'business_enabled' => tenantBusinessEnabled(),
            ],
        ];
    }

    public function balancesOnly(): array
    {
        $snapshot = $this->balances->snapshot();

        return [
            'server_time' => now()->toIso8601String(),
            'balances' => $this->formatBalances($snapshot),
            'receivables' => $this->mapByCurrencyCode($this->balances->clientReceivables(), $snapshot['currencies']),
            'person_net' => $this->mapByCurrencyCode($this->balances->personNet(), $snapshot['currencies']),
        ];
    }

    protected function clients(array $snapshot)
    {
        $clients = Client::query()->active()->orderBy('name')->get(['id', 'name', 'phone', 'company_name']);
        if ($clients->isEmpty()) {
            return [];
        }

        $outstanding = $this->bulkClientOutstanding($clients->pluck('id')->all());

        return $clients->map(fn (Client $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'company_name' => $c->company_name,
            'outstanding' => $snapshot['currencies']->mapWithKeys(
                fn ($cur) => [$cur->code => (string) ($outstanding[$c->id][$cur->id] ?? '0.00')]
            ),
        ]);
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<int, string>>
     */
    protected function bulkClientOutstanding(array $clientIds): array
    {
        $billed = ClientService::query()
            ->billable()
            ->whereIn('client_id', $clientIds)
            ->selectRaw('client_id, currency_id, SUM(amount) as total')
            ->groupBy('client_id', 'currency_id')
            ->get();

        $paid = CashPayment::query()
            ->incoming()
            ->active()
            ->where('party_type', 'client')
            ->whereIn('party_id', $clientIds)
            ->selectRaw('party_id as client_id, currency_id, SUM(amount) as total')
            ->groupBy('party_id', 'currency_id')
            ->get();

        $out = [];
        foreach ($billed as $row) {
            $out[(int) $row->client_id][(int) $row->currency_id] = Money::of($row->total);
        }
        foreach ($paid as $row) {
            $cid = (int) $row->client_id;
            $cur = (int) $row->currency_id;
            $out[$cid][$cur] = Money::sub($out[$cid][$cur] ?? '0.00', $row->total);
        }

        return $out;
    }

    protected function unpaidServices()
    {
        $services = ClientService::query()
            ->billable()
            ->with(['client:id,name', 'currency:id,code,symbol'])
            ->orderByDesc('service_date')
            ->limit(150)
            ->get(['id', 'client_id', 'title', 'amount', 'currency_id', 'service_date', 'status']);

        return $services
            ->map(fn (ClientService $s) => [
                'id' => $s->id,
                'client_id' => $s->client_id,
                'client_name' => $s->client?->name,
                'title' => $s->title,
                'amount' => (string) $s->amount,
                'currency_id' => $s->currency_id,
                'currency_code' => $s->currency?->code,
                'service_date' => optional($s->service_date)->format('Y-m-d'),
                'status' => $s->status->value ?? $s->status,
            ])
            ->values();
    }

    protected function persons(array $snapshot)
    {
        $members = Person::query()->active()->orderBy('name')->get(['id', 'name', 'relationship', 'phone']);
        if ($members->isEmpty()) {
            return [];
        }

        return $members->map(function (Person $m) use ($snapshot) {
            $net = [];
            foreach ($snapshot['currencies'] as $cur) {
                $net[$cur->code] = $m->netAmount($cur->id);
            }

            return [
                'id' => $m->id,
                'name' => $m->name,
                'relationship' => $m->relationship,
                'phone' => $m->phone,
                'net' => $net,
            ];
        });
    }

    protected function vendors()
    {
        return Vendor::query()->active()->orderBy('name')->get(['id', 'name', 'type', 'phone']);
    }

    public function formatBalances(array $snapshot): array
    {
        $out = [
            'grand' => [],
            'funds' => [],
            'methods' => [],
            'cells' => [],
        ];

        foreach ($snapshot['currencies'] as $currency) {
            $out['grand'][$currency->code] = (string) ($snapshot['grand'][$currency->id] ?? '0.00');
        }

        foreach ($snapshot['funds'] as $fund) {
            $row = ['id' => $fund->id, 'slug' => $fund->slug, 'name' => $fund->name, 'totals' => []];
            foreach ($snapshot['currencies'] as $currency) {
                $row['totals'][$currency->code] = (string) ($snapshot['fundTotals'][$fund->id][$currency->id] ?? '0.00');
            }
            $out['funds'][] = $row;
        }

        foreach ($snapshot['methods'] as $method) {
            $row = ['id' => $method->id, 'slug' => $method->slug, 'name' => $method->name, 'totals' => []];
            foreach ($snapshot['currencies'] as $currency) {
                $row['totals'][$currency->code] = (string) ($snapshot['methodTotals'][$method->id][$currency->id] ?? '0.00');
            }
            $out['methods'][] = $row;
        }

        foreach ($snapshot['funds'] as $fund) {
            foreach ($snapshot['methods'] as $method) {
                foreach ($snapshot['currencies'] as $currency) {
                    $amount = $snapshot['cells'][$fund->id][$method->id][$currency->id] ?? null;
                    if ($amount === null || Money::isZero($amount)) {
                        continue;
                    }
                    $out['cells'][] = [
                        'fund_id' => $fund->id,
                        'fund_slug' => $fund->slug,
                        'payment_method_id' => $method->id,
                        'payment_method_slug' => $method->slug,
                        'currency_code' => $currency->code,
                        'amount' => (string) $amount,
                    ];
                }
            }
        }

        return $out;
    }

    public function mapByCurrencyCode(array $byId, $currencies): array
    {
        $out = [];
        foreach ($currencies as $currency) {
            $out[$currency->code] = (string) ($byId[$currency->id] ?? '0.00');
        }

        return $out;
    }
}
