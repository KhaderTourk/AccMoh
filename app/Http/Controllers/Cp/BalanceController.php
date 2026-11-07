<?php

namespace App\Http\Controllers\Cp;

use App\Enums\TransactionType;
use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\AppSetting;
use App\Models\Currency;
use App\Models\FinancialAuditLog;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Services\Finance\AdjustmentService;
use App\Services\Finance\BalanceService;
use App\Services\Finance\FinancialLedgerService;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Rates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * الأدراج والأرصدة (تسليم 1/11 §10): كرت لكل طريقة دفع بالشيكل بسعر اليوم، وتسوية رصيد، وصفحة الدرج بدفتر حركاته.
 */
class BalanceController extends Controller
{
    use LoadsFinanceLookups;

    /** @return array{0: string, 1: \Illuminate\Support\Collection} */
    protected function tabFunds(Request $request, array $snapshot): array
    {
        $tabs = $snapshot['funds']->pluck('slug')->all();
        $tab = $request->query('tab');
        if (! in_array($tab, array_merge($tabs, ['all']), true)) {
            $tab = in_array('business', $tabs, true) ? 'business' : ($tabs[0] ?? 'all');
        }
        $funds = $tab === 'all' ? $snapshot['funds'] : $snapshot['funds']->where('slug', $tab)->values();

        return [$tab, $funds];
    }

    public function index(BalanceService $balances)
    {
        $request = request();
        $snapshot = $balances->snapshot();
        [$tab, $funds] = $this->tabFunds($request, $snapshot);
        $rates = Rates::today();
        $cards = [];
        $total = 0.0;
        foreach ($snapshot['methods'] as $method) {
            $byCurrency = [];
            $ils = 0.0;
            $byFund = [];
            foreach ($snapshot['currencies'] as $c) {
                $sum = 0.0;
                foreach ($funds as $fund) {
                    $v = (float) ($snapshot['cells'][$fund->id][$method->id][$c->id] ?? 0);
                    $sum += $v;
                    $byFund[$fund->id] = ($byFund[$fund->id] ?? 0) + Rates::toIls($v, $c->code);
                }
                if (abs($sum) >= 0.005) {
                    $byCurrency[] = ['code' => $c->code, 'id' => $c->id, 'label' => ($sum < 0 ? '−' : '').$c->format(abs($sum)), 'negative' => $sum < 0];
                }
                $ils += Rates::toIls($sum, $c->code);
            }
            $total += $ils;
            $cards[] = ['method' => $method, 'ils' => round($ils, 2), 'currencies' => $byCurrency, 'byFund' => $byFund];
        }

        return view('cp.finance.balances.index', [
            'tab' => $tab,
            'funds' => $snapshot['funds'],
            'tabFunds' => $funds,
            'cards' => $cards,
            'total' => round($total, 2),
            'rates' => $rates,
            'currencies' => $snapshot['currencies'],
        ]);
    }

    public function show(Request $request, PaymentMethod $method, BalanceService $balances)
    {
        $snapshot = $balances->snapshot();
        [$tab, $funds] = $this->tabFunds($request, $snapshot);
        $from = DateRange::normalize((string) $request->query('from')) ?: null;
        $to = DateRange::normalize((string) $request->query('to')) ?: null;
        $cur = $snapshot['currencies']->firstWhere('code', (string) $request->query('cur'));

        $entries = LedgerEntry::query()
            ->where('payment_method_id', $method->id)
            ->whereIn('fund_id', $funds->pluck('id'))
            ->when($cur, fn ($q) => $q->where('currency_id', $cur->id))
            ->with(['currency', 'fund'])
            ->orderBy('occurred_on')->orderBy('id')
            ->get();

        $running = [];
        $rows = [];
        $opening = [];
        foreach ($entries as $e) {
            $running[$e->currency_id] = Money::add($running[$e->currency_id] ?? '0', $e->amount);
            $day = $e->occurred_on->toDateString();
            if ($from && $day < $from) {
                $opening[$e->currency_id] = $running[$e->currency_id];

                continue;
            }
            if ($to && $day > $to) {
                continue;
            }
            $rows[] = ['entry' => $e, 'after' => $running[$e->currency_id]];
        }

        $cards = [];
        foreach ($snapshot['currencies'] as $c) {
            $sum = 0.0;
            foreach ($funds as $fund) {
                $sum += (float) ($snapshot['cells'][$fund->id][$method->id][$c->id] ?? 0);
            }
            if (abs($sum) >= 0.005 || ($cur && $cur->id === $c->id)) {
                $cards[] = ['currency' => $c, 'amount' => $sum];
            }
        }

        return view('cp.finance.balances.show', [
            'method' => $method,
            'tab' => $tab,
            'funds' => $snapshot['funds'],
            'cards' => $cards,
            'rows' => array_reverse($rows),
            'opening' => $opening,
            'currencies' => $snapshot['currencies'],
            'cur' => $cur,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** «تسوية رصيد»: بتسجّل حركة بالفرق بين الموجود فعلياً واللي بالنظام، مع ملاحظة إجبارية. */
    public function settle(Request $request, BalanceService $balances, FinancialLedgerService $ledger)
    {
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'actual' => ['required', 'numeric'],
            'notes' => ['required', 'string', 'max:500'],
        ], ['notes.required' => 'اكتب سبب التسوية.']);

        $current = $balances->cash((int) $data['fund_id'], (int) $data['payment_method_id'], (int) $data['currency_id']);
        $diff = Money::sub(Money::of($data['actual']), $current);
        if (Money::isZero($diff)) {
            throw ValidationException::withMessages(['actual' => 'الموجود نفس اللي بالنظام، ما في فرق.']);
        }

        DB::transaction(function () use ($data, $diff, $ledger) {
            $group = (string) Str::uuid();
            $ledger->post([[
                'type' => TransactionType::Adjustment,
                'fund_id' => $data['fund_id'],
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $data['currency_id'],
                'amount' => $diff,
                'occurred_on' => now()->toDateString(),
                'description' => 'تسوية رصيد',
                'notes' => $data['notes'],
            ]], $group);
            $entry = LedgerEntry::query()->where('group_id', $group)->firstOrFail();
            FinancialAuditLog::record('created', $entry, ['settle' => $diff]);
        });

        return back()->with('success', 'انسجّلت التسوية بالفرق '.Currency::query()->find($data['currency_id'])->format($diff).'.');
    }

    /** سعر الدولار والدينار اليوم بالشيكل، للمجاميع بس (الحركات بتنحفظ بسعرها وقت التسجيل). */
    public function rates(Request $request)
    {
        $data = $request->validate(['rates' => ['array'], 'rates.*' => ['nullable', 'numeric', 'gt:0']]);
        foreach (Currency::query()->where('code', '!=', 'ILS')->pluck('code') as $code) {
            if (array_key_exists($code, $data['rates'] ?? [])) {
                AppSetting::put('rate.'.$code, filled($data['rates'][$code]) ? (string) $data['rates'][$code] : null);
            }
        }
        Rates::forget();

        return back()->with('success', 'انحفظ سعر اليوم.');
    }

    public function storeOpening(Request $request, AdjustmentService $adjustments)
    {
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_on' => DateRange::inputRules(),
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $adjustments->opening($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.balances.index')->with('success', 'تم تسجيل الرصيد الافتتاحي.');
    }
}
