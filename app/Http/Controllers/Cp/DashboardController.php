<?php

namespace App\Http\Controllers\Cp;

use App\Enums\LoanDirection;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\FamilyLoan;
use App\Models\FamilyMember;
use App\Models\FundTransfer;
use App\Models\LedgerEntry;
use App\Support\Money;
use App\Services\Finance\BalanceService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(BalanceService $balances)
    {
        $snapshot = $balances->snapshot();
        $receivables = $balances->clientReceivables();
        $iOwe = $balances->familyBalance(LoanDirection::Borrowed);
        $theyOwe = $balances->familyBalance(LoanDirection::Lent);

        $recent = LedgerEntry::query()
            ->with(['fund', 'paymentMethod', 'currency', 'related'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        $revenueByMonth = [];
        $expenseByMonth = [];
        foreach ($snapshot['currencies'] as $currency) {
            foreach ($months as $month) {
                $key = $month->format('Y-m');
                $revenueByMonth[$currency->code][$key] = (float) LedgerEntry::query()
                    ->where('currency_id', $currency->id)
                    ->where('transaction_type', TransactionType::ClientPayment)
                    ->where('is_reversal', false)
                    ->whereYear('occurred_on', $month->year)
                    ->whereMonth('occurred_on', $month->month)
                    ->sum('amount');

                $expenseByMonth[$currency->code][$key] = abs((float) LedgerEntry::query()
                    ->where('currency_id', $currency->id)
                    ->where('transaction_type', TransactionType::Expense)
                    ->where('is_reversal', false)
                    ->whereYear('occurred_on', $month->year)
                    ->whereMonth('occurred_on', $month->month)
                    ->sum('amount'));
            }
        }

        $methodDistribution = [];
        foreach ($snapshot['methods'] as $method) {
            foreach ($snapshot['currencies'] as $currency) {
                $methodDistribution[$method->slug][$currency->code] = (float) ($snapshot['methodTotals'][$method->id][$currency->id] ?? 0);
            }
        }

        return view('cp.dashboard', [
            'snapshot' => $snapshot,
            'receivables' => $receivables,
            'iOwe' => $iOwe,
            'theyOwe' => $theyOwe,
            'recent' => $recent,
            'months' => $months->map(fn ($m) => $m->format('Y-m'))->values(),
            'monthKeys' => $months->map(fn ($m) => $m->format('Y-m'))->values(),
            'revenueByMonth' => $revenueByMonth,
            'expenseByMonth' => $expenseByMonth,
            'methodDistribution' => $methodDistribution,
            'topIndebted' => $balances->topIndebtedClients(5),
            'topPaying' => $balances->topPayingClients(5),
            'counts' => [
                'clients' => Client::query()->count(),
                'family' => FamilyMember::query()->count(),
                'open_services' => ClientService::query()->billable()->get()->filter(
                    fn ($s) => Money::isPositive($s->remainingAmount())
                )->count(),
                'open_loans' => FamilyLoan::query()->active()->whereIn('status', ['open', 'partial'])->count(),
            ],
        ]);
    }
}
