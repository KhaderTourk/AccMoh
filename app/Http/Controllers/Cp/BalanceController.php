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
use App\Support\DrawerPlaces;
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
        // «عمل» و«شخصي» رصيد واحد (قرار 11/11/2026): الدرج بيبيّن مجموع كل الصناديق.
        return ['all', $snapshot['funds']];
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
                // كل العملات بتظهر بالكرت حتى لو صفر (ملاحظات الأدراج 12/11/2026).
                $byCurrency[] = ['code' => $c->code, 'id' => $c->id, 'name' => DrawerPlaces::curName($c->code, $c->name), 'symbol' => $c->symbol, 'amount' => round($sum, 2),
                    'label' => ($sum < 0 ? '−' : '').number_format(abs($sum), 2), 'negative' => $sum < -0.004, 'zero' => abs($sum) < 0.005];
                $ils += Rates::toIls($sum, $c->code);
            }
            $total += $ils;
            $cards[] = ['method' => $method, 'ils' => round($ils, 2), 'currencies' => $byCurrency, 'byFund' => $byFund];
        }

        // بيانات نافذة التحويل: رصيد كل درج بكل عملة.
        $wizard = [
            'methods' => collect($cards)->map(fn ($c) => [
                'id' => $c['method']->id,
                'name' => $c['method']->name,
                'bal' => collect($c['currencies'])->mapWithKeys(fn ($x) => [$x['code'] => $x['amount']])->all(),
            ])->values()->all(),
            'currencies' => $snapshot['currencies']->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'name' => DrawerPlaces::curName($c->code, $c->name), 'symbol' => $c->symbol])->values()->all(),
        ];

        return view('cp.finance.balances.index', [
            'tab' => $tab,
            'funds' => $snapshot['funds'],
            'tabFunds' => $funds,
            'cards' => $cards,
            'total' => round($total, 2),
            'rates' => $rates,
            'currencies' => $snapshot['currencies'],
            'wizard' => $wizard,
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
            $cards[] = ['currency' => $c, 'amount' => round($sum, 2)];
        }
        $places = DrawerPlaces::get($method->id);

        return view('cp.finance.balances.show', [
            'method' => $method,
            'tab' => $tab,
            'funds' => $snapshot['funds'],
            'cards' => $cards,
            'places' => $places,
            'rows' => array_reverse($rows),
            'opening' => $opening,
            'currencies' => $snapshot['currencies'],
            'cur' => $cur,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * توزيع الدرج على أماكن (للعرض بس، قرار 12/11/2026): ما بيلمس الحركات ولا الأرصدة.
     * الموزّع بكل عملة ما بيزيد عن رصيد الدرج بهالعملة.
     */
    public function places(Request $request, PaymentMethod $method, BalanceService $balances)
    {
        $codes = Currency::query()->pluck('code', 'id');
        $data = $request->validate([
            'places' => ['array', 'max:30'],
            'places.*.name' => ['nullable', 'string', 'max:60'],
            'places.*.amounts' => ['array'],
            'places.*.amounts.*' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $clean = [];
        $sums = [];
        foreach ($data['places'] ?? [] as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            $amounts = [];
            foreach ($codes as $code) {
                $v = round((float) ($p['amounts'][$code] ?? 0), 2);
                if ($v > 0) {
                    $amounts[$code] = $v;
                    $sums[$code] = ($sums[$code] ?? 0) + $v;
                }
            }
            if ($name === '' && ! $amounts) {
                continue;
            }
            if ($name === '') {
                throw ValidationException::withMessages(['places' => 'اكتب اسم لكل مكان.']);
            }
            $clean[] = ['name' => $name, 'amounts' => $amounts];
        }

        foreach ($codes as $id => $code) {
            $have = max(0, (float) $balances->drawerCash($method->id, (int) $id));
            if (($sums[$code] ?? 0) > $have + 0.004) {
                throw ValidationException::withMessages(['places' => 'الموزّع بال'.Currency::query()->find($id)->name.' أكتر من رصيد الدرج.']);
            }
        }

        DrawerPlaces::put($method->id, $clean);

        return back()->with('success', 'انحفظ توزيع '.$method->name.'.');
    }

    /** «تسوية رصيد»: بتسجّل حركة بالفرق بين الموجود فعلياً واللي بالنظام، مع ملاحظة إجبارية. */
    public function settle(Request $request, BalanceService $balances, FinancialLedgerService $ledger)
    {
        $request->mergeIfMissing(['fund_id' => defaultFundId()]);
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'actual' => ['required', 'numeric'],
            'notes' => ['required', 'string', 'max:500'],
        ], ['notes.required' => 'اكتب سبب التسوية.']);

        $current = $balances->drawerCash((int) $data['payment_method_id'], (int) $data['currency_id']);
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
    /** سعر مقترح من مصدر رسمي (اقتراح بس، ما بيحفظ إشي). */
    public function suggestRates()
    {
        $s = \App\Support\OfficialRates::suggest();

        return response()->json($s ? $s + ['ok' => true] : ['ok' => false]);
    }

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
        $request->mergeIfMissing(['fund_id' => defaultFundId()]);
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
