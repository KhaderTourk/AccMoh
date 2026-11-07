<?php

namespace App\Http\Controllers\Cp;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\LedgerEntry;
use App\Services\Finance\BalanceService;
use App\Services\Finance\PositionService;
use App\Support\Rates;

/**
 * الرئيسية (تسليم 1/11 §9): معي · إلي · علي، والصافي، وهالشهر، والاشتراكات بانتظار التأكيد، وبدها متابعة، وآخر الحركات.
 */
class DashboardController extends Controller
{
    public const IN = [TransactionType::IncomingPayment, TransactionType::ClientPayment, TransactionType::FamilyLoanReceived, TransactionType::FamilyLoanRepaymentReceived];

    public const OUT = [TransactionType::OutgoingPayment, TransactionType::Expense, TransactionType::FamilyLoanGiven, TransactionType::FamilyLoanRepaymentPaid];

    public function index(BalanceService $balances, PositionService $positions)
    {
        $snapshot = $balances->snapshot();
        $codes = Currency::query()->pluck('code', 'id');
        $withMe = 0.0;
        foreach ($snapshot['grand'] as $currencyId => $amount) {
            $withMe += Rates::toIls((float) $amount, $codes[$currencyId] ?? 'ILS');
        }
        $pos = $positions->summary();
        $owed = array_sum($pos['owed']);
        $owe = array_sum($pos['owe']);

        // هالشهر وآخر 6 أشهر: الدفعات الواردة والصادرة بس، بدون التحويلات بين الأدراج، بالشيكل
        $start = now()->startOfMonth()->subMonths(5);
        $flow = [];
        for ($i = 0; $i < 6; $i++) {
            $flow[$start->copy()->addMonths($i)->format('Y-m')] = ['in' => 0.0, 'out' => 0.0];
        }
        $rows = LedgerEntry::query()
            ->where('is_reversal', false)
            ->where('occurred_on', '>=', $start->toDateString())
            ->whereIn('transaction_type', array_merge(self::IN, self::OUT))
            ->get(['transaction_type', 'currency_id', 'amount', 'occurred_on']);
        foreach ($rows as $r) {
            $key = $r->occurred_on->format('Y-m');
            if (! isset($flow[$key])) {
                continue;
            }
            $v = abs(Rates::toIls((float) $r->amount, $codes[$r->currency_id] ?? 'ILS'));
            $flow[$key][in_array($r->transaction_type, self::IN, true) ? 'in' : 'out'] += $v;
        }

        $recent = LedgerEntry::query()
            ->withoutArchivedParties()
            ->whereNotIn('transaction_type', [TransactionType::TransferFee, TransactionType::TransferIn])
            ->with(['fund', 'paymentMethod', 'currency', 'related'])
            ->orderByDesc('occurred_on')->orderByDesc('id')
            ->limit(8)->get();

        $subs = tenantBusinessEnabled()
            ? ClientSubscriptionController::pending(now()->format('Y-m'))->groupBy(fn ($r) => $r['client']->id)
            : collect();

        return view('cp.dashboard', [
            'withMe' => round($withMe, 2),
            'owed' => $pos['owed'],
            'owe' => $pos['owe'],
            'net' => round($withMe + $owed - $owe, 2),
            'flow' => $flow,
            'month' => $flow[now()->format('Y-m')],
            'subs' => $subs,
            'topClients' => $pos['topClients'],
            'topVendors' => $pos['topVendors'],
            'recent' => $recent,
            'rates' => Rates::today(),
        ]);
    }
}
