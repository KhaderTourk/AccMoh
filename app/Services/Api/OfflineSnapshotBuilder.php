<?php

namespace App\Services\Api;

use App\Enums\LoanDirection;
use App\Models\Client;
use App\Models\ClientPayment;
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
use Illuminate\Support\Facades\DB;

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
                'expense_categories' => ExpenseCategory::query()
                    ->active()
                    ->when(! tenantBusinessEnabled(), fn ($q) => $q->where(function ($qq) {
                        $qq->whereNull('fund_slug')->orWhere('fund_slug', '!=', 'business');
                    }))
                    ->get(['id', 'name', 'fund_slug']),
                'service_types' => tenantBusinessEnabled()
                    ? ServiceType::query()->active()->get(['id', 'name', 'default_price', 'default_currency_id'])
                    : collect(),
            ],
            'clients' => tenantBusinessEnabled() ? $this->clients($snapshot) : [],
            'unpaid_services' => tenantBusinessEnabled() ? $this->unpaidServices() : [],
            'family_members' => $this->familyMembers($snapshot),
            'open_loans' => $this->openLoans(),
            'balances' => $this->formatBalances($snapshot),
            'receivables' => tenantBusinessEnabled()
                ? $this->mapByCurrencyCode($this->balances->clientReceivables(), $snapshot['currencies'])
                : $this->mapByCurrencyCode([], $snapshot['currencies']),
            'family_i_owe' => $this->mapByCurrencyCode($this->balances->familyBalance(LoanDirection::Borrowed), $snapshot['currencies']),
            'family_they_owe' => $this->mapByCurrencyCode($this->balances->familyBalance(LoanDirection::Lent), $snapshot['currencies']),
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
            'family_i_owe' => $this->mapByCurrencyCode($this->balances->familyBalance(LoanDirection::Borrowed), $snapshot['currencies']),
            'family_they_owe' => $this->mapByCurrencyCode($this->balances->familyBalance(LoanDirection::Lent), $snapshot['currencies']),
        ];
    }

    protected function clients(array $snapshot)
    {
        $clients = Client::query()->active()->orderBy('name')->get(['id', 'name', 'contact_name', 'phone', 'company_name']);
        if ($clients->isEmpty()) {
            return [];
        }

        $outstanding = $this->bulkClientOutstanding($clients->pluck('id')->all());

        return $clients->map(fn (Client $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'contact_name' => $c->contact_name,
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

        $paid = ClientPayment::query()
            ->active()
            ->whereIn('client_id', $clientIds)
            ->selectRaw('client_id, currency_id, SUM(amount) as total')
            ->groupBy('client_id', 'currency_id')
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

    protected function familyMembers(array $snapshot)
    {
        $members = FamilyMember::query()->active()->orderBy('name')->get(['id', 'name', 'relationship', 'phone']);
        if ($members->isEmpty()) {
            return [];
        }

        $remaining = $this->bulkFamilyRemaining($members->pluck('id')->all());

        return $members->map(function (FamilyMember $m) use ($snapshot, $remaining) {
            $iOwe = [];
            $theyOwe = [];
            foreach ($snapshot['currencies'] as $cur) {
                $iOwe[$cur->code] = (string) ($remaining[$m->id][LoanDirection::Borrowed->value][$cur->id] ?? '0.00');
                $theyOwe[$cur->code] = (string) ($remaining[$m->id][LoanDirection::Lent->value][$cur->id] ?? '0.00');
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

    /**
     * @param  list<int>  $memberIds
     * @return array<int, array<string, array<int, string>>>
     */
    protected function bulkFamilyRemaining(array $memberIds): array
    {
        $loanTotals = FamilyLoan::query()
            ->active()
            ->whereIn('family_member_id', $memberIds)
            ->selectRaw('family_member_id, direction, currency_id, SUM(amount) as total')
            ->groupBy('family_member_id', 'direction', 'currency_id')
            ->get();

        $repaid = DB::table('family_loan_repayment_items as i')
            ->join('family_loan_repayments as r', 'r.id', '=', 'i.family_loan_repayment_id')
            ->join('family_loans as l', 'l.id', '=', 'i.family_loan_id')
            ->where('r.is_reversed', false)
            ->where('l.is_reversed', false)
            ->whereIn('l.family_member_id', $memberIds)
            ->selectRaw('l.family_member_id, l.direction, l.currency_id, SUM(i.allocated_amount) as total')
            ->groupBy('l.family_member_id', 'l.direction', 'l.currency_id')
            ->get();

        $out = [];
        foreach ($loanTotals as $row) {
            $direction = is_string($row->direction) ? $row->direction : $row->direction->value;
            $out[(int) $row->family_member_id][$direction][(int) $row->currency_id] = Money::of($row->total);
        }
        foreach ($repaid as $row) {
            $mid = (int) $row->family_member_id;
            $direction = (string) $row->direction;
            $cur = (int) $row->currency_id;
            $out[$mid][$direction][$cur] = Money::sub($out[$mid][$direction][$cur] ?? '0.00', $row->total);
        }

        return $out;
    }

    protected function openLoans()
    {
        $loans = FamilyLoan::query()
            ->active()
            ->whereIn('status', ['open', 'partial'])
            ->with(['familyMember:id,name', 'currency:id,code'])
            ->orderBy('loan_date')
            ->limit(200)
            ->get();

        if ($loans->isEmpty()) {
            return [];
        }

        $repaid = DB::table('family_loan_repayment_items as i')
            ->join('family_loan_repayments as r', 'r.id', '=', 'i.family_loan_repayment_id')
            ->where('r.is_reversed', false)
            ->whereIn('i.family_loan_id', $loans->pluck('id'))
            ->selectRaw('i.family_loan_id, SUM(i.allocated_amount) as total')
            ->groupBy('i.family_loan_id')
            ->pluck('total', 'family_loan_id');

        return $loans
            ->map(function (FamilyLoan $l) use ($repaid) {
                $remaining = Money::sub($l->amount, $repaid[$l->id] ?? 0);
                if (! Money::isPositive($remaining)) {
                    return null;
                }

                return [
                    'id' => $l->id,
                    'family_member_id' => $l->family_member_id,
                    'family_member' => $l->familyMember?->name,
                    'direction' => $l->direction->value ?? $l->direction,
                    'amount' => (string) $l->amount,
                    'remaining' => $remaining,
                    'currency_id' => $l->currency_id,
                    'currency_code' => $l->currency?->code,
                    'loan_date' => optional($l->loan_date)->format('Y-m-d'),
                    'status' => $l->status->value ?? $l->status,
                ];
            })
            ->filter()
            ->values();
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

        // Only non-zero cells — smaller payload on slow networks
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
