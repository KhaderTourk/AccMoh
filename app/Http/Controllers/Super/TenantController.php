<?php

namespace App\Http\Controllers\Super;

use App\Enums\LoanDirection;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Expense;
use App\Models\FamilyLoan;
use App\Models\FamilyMember;
use App\Models\LedgerEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\BalanceService;
use App\Services\Tenancy\TenantProvisioningService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function dashboard()
    {
        return view('super.dashboard', [
            'tenantsCount' => Tenant::query()->count(),
            'activeTenants' => Tenant::query()->where('is_active', true)->count(),
            'usersCount' => User::query()->whereNotNull('tenant_id')->count(),
        ]);
    }

    public function index()
    {
        $tenants = Tenant::query()
            ->with('owner')
            ->orderByDesc('id')
            ->paginate(20);

        return view('super.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('super.tenants.form', ['tenant' => new Tenant(['business_enabled' => true, 'is_active' => true])]);
    }

    public function store(Request $request, TenantProvisioningService $provisioning)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:tenants,slug'],
            'business_enabled' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:6'],
        ]);

        $data['business_enabled'] = $request->boolean('business_enabled', true);
        $tenant = $provisioning->create($data);

        return redirect()->route('super.tenants.show', $tenant)
            ->with('success', 'تم إنشاء النسخة ومستخدمها بنجاح.');
    }

    public function show(Tenant $tenant)
    {
        $tenant->load('owner');

        return view('super.tenants.show', compact('tenant'));
    }

    public function finances(Tenant $tenant, BalanceService $balances)
    {
        return $this->withTenantContext($tenant, function () use ($tenant, $balances) {
            $snapshot = $balances->snapshot();
            $receivables = $tenant->business_enabled ? $balances->clientReceivables() : [];
            $iOwe = $balances->familyBalance(LoanDirection::Borrowed);
            $theyOwe = $balances->familyBalance(LoanDirection::Lent);

            $recent = LedgerEntry::query()
                ->with(['fund', 'paymentMethod', 'currency'])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->limit(30)
                ->get();

            $openLoans = FamilyLoan::query()
                ->active()
                ->whereIn('status', ['open', 'partial'])
                ->with(['familyMember', 'currency', 'paymentMethod'])
                ->orderByDesc('loan_date')
                ->limit(50)
                ->get();

            $counts = [
                'clients' => $tenant->business_enabled ? Client::query()->count() : 0,
                'family' => FamilyMember::query()->count(),
                'open_loans' => FamilyLoan::query()->active()->whereIn('status', ['open', 'partial'])->count(),
                'users' => User::query()->where('tenant_id', $tenant->id)->count(),
            ];

            return view('super.tenants.finances', compact(
                'tenant', 'snapshot', 'receivables', 'iOwe', 'theyOwe', 'recent', 'openLoans', 'counts'
            ));
        });
    }

    public function reports(Tenant $tenant, BalanceService $balances)
    {
        return $this->withTenantContext($tenant, function () use ($tenant, $balances) {
            $snapshot = $balances->snapshot();
            $receivables = $tenant->business_enabled ? $balances->clientReceivables() : [];
            $iOwe = $balances->familyBalance(LoanDirection::Borrowed);
            $theyOwe = $balances->familyBalance(LoanDirection::Lent);

            $clientSummary = $tenant->business_enabled
                ? Client::query()->orderBy('name')->get()->map(function (Client $client) use ($snapshot) {
                    $rows = [];
                    foreach ($snapshot['currencies'] as $currency) {
                        $billed = $client->billedAmount($currency->id);
                        $paid = $client->paidAmount($currency->id);
                        $due = $client->outstandingAmount($currency->id);
                        if (\App\Support\Money::isZero($billed) && \App\Support\Money::isZero($paid)) {
                            continue;
                        }
                        $rows[] = compact('currency', 'billed', 'paid', 'due');
                    }

                    return ['client' => $client, 'rows' => $rows];
                })->filter(fn ($r) => $r['rows'] !== [])
                : collect();

            $familySummary = FamilyMember::query()->orderBy('name')->get()->map(function (FamilyMember $member) use ($snapshot) {
                $rows = [];
                foreach ($snapshot['currencies'] as $currency) {
                    $owe = $member->iOweAmount($currency->id);
                    $owed = $member->theyOweAmount($currency->id);
                    if (\App\Support\Money::isZero($owe) && \App\Support\Money::isZero($owed)) {
                        continue;
                    }
                    $rows[] = compact('currency', 'owe', 'owed');
                }

                return ['member' => $member, 'rows' => $rows];
            })->filter(fn ($r) => $r['rows'] !== []);

            $revenue = $tenant->business_enabled
                ? ClientPayment::query()->active()->with(['client', 'currency'])->orderByDesc('payment_date')->limit(50)->get()
                : collect();

            $expenses = Expense::query()->active()->with(['fund', 'currency'])->orderByDesc('expense_date')->limit(50)->get();

            $openLoans = FamilyLoan::query()
                ->active()
                ->whereIn('status', ['open', 'partial'])
                ->with(['familyMember', 'currency'])
                ->orderBy('loan_date')
                ->get();

            return view('super.tenants.reports', compact(
                'tenant', 'snapshot', 'receivables', 'iOwe', 'theyOwe',
                'clientSummary', 'familySummary', 'revenue', 'expenses', 'openLoans'
            ));
        });
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withTenantContext(Tenant $tenant, callable $callback): mixed
    {
        TenantContext::set((int) $tenant->id);
        request()->attributes->set('tenant', $tenant);

        try {
            return $callback();
        } finally {
            TenantContext::clear();
            request()->attributes->remove('tenant');
        }
    }

    public function edit(Tenant $tenant)
    {
        $tenant->load('owner');

        return view('super.tenants.form', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant, TenantProvisioningService $provisioning)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'business_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_password' => ['nullable', 'string', 'min:6'],
        ]);

        $wasBusiness = $tenant->business_enabled;
        $tenant->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'business_enabled' => $request->boolean('business_enabled'),
            'is_active' => $request->boolean('is_active', true),
            'notes' => $data['notes'] ?? null,
        ]);

        if ($tenant->business_enabled && ! $wasBusiness) {
            $provisioning->ensureBusinessFund($tenant);
            $provisioning->seedCatalog($tenant);
        }

        if ($tenant->owner) {
            $ownerUpdates = [];
            if (! empty($data['owner_name'])) {
                $ownerUpdates['name'] = $data['owner_name'];
            }
            if (! empty($data['owner_password'])) {
                $ownerUpdates['password'] = Hash::make($data['owner_password']);
            }
            if ($ownerUpdates !== []) {
                $tenant->owner->update($ownerUpdates);
            }
        }

        return redirect()->route('super.tenants.show', $tenant)->with('success', 'تم تحديث النسخة.');
    }
}
