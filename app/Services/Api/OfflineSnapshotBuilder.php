<?php

namespace App\Services\Api;

use App\Enums\LoanDirection;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\FamilyLoan;
use App\Models\FamilyMember;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
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
                'funds' => Fund::query()->orderBy('id')->get(['id', 'name', 'slug']),
                'expense_categories' => ExpenseCategory::query()->active()->get(['id', 'name', 'fund_slug']),
                'service_types' => ServiceType::query()->active()->get(['id', 'name', 'default_price', 'default_currency_id']),
            ],
            'clients' => $this->clients($snapshot),
            'unpaid_services' => $this->unpaidServices(),
            'family_members' => $this->familyMembers($snapshot),
            'open_loans' => $this->openLoans(),
            'balances' => $this->formatBalances($snapshot),
            'receivables' => $this->mapByCurrencyCode($this->balances->clientReceivables(), $snapshot['currencies']),
            'family_i_owe' => $this->mapByCurrencyCode($this->balances->familyBalance(LoanDirection::Borrowed), $snapshot['currencies']),
            'family_they_owe' => $this->mapByCurrencyCode($this->balances->familyBalance(LoanDirection::Lent), $snapshot['currencies']),
        ];
    }

    public function balancesOnly(): array
    {
        $snapshot = $this->balances->snapshot();

        return [
            'server_time' => now()->toIso8601String(),
            'balances' => $this->formatBalances($snapshot),
            'receivables' => $this->mapByCurrencyCode($this->balances->clientReceivables(), $snapshot['currencies']),
            'family_i_owe' => $this->mapByCurrencyCode($this->balances->familyBalance(LoanDirection::Borrowed), $snapshot['currencies']),
            'family_they_owe' => $this->mapByCurrencyCode($this->balances->familyBalance(LoanDirection::Lent), $snapshot['currencies']),
        ];
    }

    protected function clients(array $snapshot)
    {
        return Client::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'company_name' => $c->company_name,
                'outstanding' => $snapshot['currencies']->mapWithKeys(
                    fn ($cur) => [$cur->code => $c->outstandingAmount($cur->id)]
                ),
            ]);
    }

    protected function unpaidServices()
    {
        return ClientService::query()
            ->billable()
            ->with(['client:id,name', 'currency:id,code,symbol'])
            ->orderByDesc('service_date')
            ->limit(200)
            ->get()
            ->filter(fn ($s) => Money::isPositive($s->remainingAmount()))
            ->values()
            ->map(fn (ClientService $s) => [
                'id' => $s->id,
                'client_id' => $s->client_id,
                'client_name' => $s->client?->name,
                'title' => $s->title,
                'amount' => (string) $s->amount,
                'paid' => $s->paidAmount(),
                'remaining' => $s->remainingAmount(),
                'currency_id' => $s->currency_id,
                'currency_code' => $s->currency?->code,
                'service_date' => optional($s->service_date)->format('Y-m-d'),
                'status' => $s->status->value ?? $s->status,
            ]);
    }

    protected function familyMembers(array $snapshot)
    {
        return FamilyMember::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (FamilyMember $m) use ($snapshot) {
                $iOwe = [];
                $theyOwe = [];
                foreach ($snapshot['currencies'] as $cur) {
                    $iOwe[$cur->code] = $m->iOweAmount($cur->id);
                    $theyOwe[$cur->code] = $m->theyOweAmount($cur->id);
                }

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'relationship' => $m->relationship,
                    'phone' => $m->phone,
                    'i_owe' => $iOwe,
                    'they_owe' => $theyOwe,
                ];
            });
    }

    protected function openLoans()
    {
        return FamilyLoan::query()
            ->active()
            ->whereIn('status', ['open', 'partial'])
            ->with(['familyMember:id,name', 'currency:id,code'])
            ->orderBy('loan_date')
            ->get()
            ->filter(fn ($l) => Money::isPositive($l->remainingAmount()))
            ->values()
            ->map(fn (FamilyLoan $l) => [
                'id' => $l->id,
                'family_member_id' => $l->family_member_id,
                'family_member' => $l->familyMember?->name,
                'direction' => $l->direction->value ?? $l->direction,
                'amount' => (string) $l->amount,
                'remaining' => $l->remainingAmount(),
                'currency_id' => $l->currency_id,
                'currency_code' => $l->currency?->code,
                'loan_date' => optional($l->loan_date)->format('Y-m-d'),
                'status' => $l->status->value ?? $l->status,
            ]);
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
                    if ($amount === null) {
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
