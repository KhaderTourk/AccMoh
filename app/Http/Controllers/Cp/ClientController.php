<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Services\Export\PdfExporter;
use App\Services\Finance\ClientStatementService;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Phone;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $currencies = Currency::query()->active()->get();
        $ils = Currency::byCode('ILS');
        $today = \Carbon\CarbonImmutable::today();
        $monthStart = $today->startOfMonth()->toDateString();

        // الاشتراكات الشغّالة لكل زبون (قرار 15، 14/11/2026): بتنعدّل من «تعديل الزبون» بس.
        $subs = Schema::hasTable('client_subscriptions')
            ? \App\Models\ClientSubscription::query()
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today->toDateString()))
                ->orderBy('id')->get(['id', 'client_id', 'service_type_id', 'title', 'amount'])
                ->groupBy('client_id')
            : collect();

        // Every client (archived included) in one list; filtering and search happen in the browser.
        $clients = Client::query()
            ->orderByRaw("COALESCE(NULLIF(company_name, ''), name)")
            ->get()
            ->map(function (Client $client) use ($currencies, $subs, $ils, $monthStart, $today) {
                $balances = [];
                foreach ($currencies as $currency) {
                    $due = $client->outstandingAmount($currency->id);
                    if (! Money::isZero($due)) {
                        $balances[] = [
                            'code' => $currency->code,
                            'amount' => (float) $due,
                            'label' => $currency->format(Money::abs($due)),
                        ];
                    }
                }
                $activity = \App\Support\PartyActivity::forClient($client);
                // «آخر دفعة»: قبل كم يوم، ولو ما دفع أبداً من أول حركة (قرار 15: بدون «ما دفع إشي»).
                $since = $activity['last_pay'] ?: $activity['first_move'];
                $paidThisMonth = $activity['last_pay'] && $activity['last_pay'] >= $monthStart;

                return [
                    'id' => $client->id,
                    'org' => (string) $client->organization(),
                    'name' => $client->personName(),
                    'phone' => (string) $client->phone,
                    'notes' => (string) $client->notes,
                    'archived' => ! $client->is_active,
                    'subs' => $subs->get($client->id, collect())->map(fn ($s) => [
                        'id' => $s->id, 'type' => $s->service_type_id, 'title' => $s->title, 'amount' => (float) $s->amount,
                    ])->values()->all(),
                    'balances' => $balances,
                    'ils' => (float) $client->outstandingAmount($ils->id),
                    'open' => (float) $client->openingBalance($ils->id, $monthStart),
                    'payDays' => $since ? (int) \Carbon\CarbonImmutable::parse($since)->diffInDays($today) : null,
                    'paidMonth' => $paidThisMonth,
                    ...$activity,
                    'show' => route('cp.clients.show', $client),
                    'pdf' => route('cp.clients.export-pdf', $client),
                    'update' => route('cp.clients.update', $client),
                    'archive' => route('cp.clients.destroy', $client),
                    'restore' => route('cp.clients.restore', $client),
                ];
            })
            ->values();

        $currencyFormats = $currencies->mapWithKeys(fn (Currency $c) => [$c->code => ['symbol' => $c->symbol]])->all();
        $flash = (int) $request->query('flash');
        $head = $this->headStats($clients, $ils, $today);
        $serviceTypes = \App\Models\ServiceType::query()->where('is_active', true)->with('group')->orderBy('name')
            ->get(['id', 'name', 'service_group_id', 'default_price'])
            ->sortBy(fn ($t) => [$t->group ? 0 : 1, $t->group?->sort_order ?? 0, $t->group?->name ?? '', $t->name])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'group' => $t->group?->name, 'price' => $t->default_price !== null ? (float) $t->default_price : null])
            ->values();

        return view('cp.finance.clients.index', compact('clients', 'currencyFormats', 'flash', 'head', 'serviceTypes'));
    }

    /**
     * كروت رأس شاشة الزبائن (قرار 16، 15/11/2026). كل أرقام «هالشهر» حسب تاريخ الحركة نفسها مش تاريخ التسجيل.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $clients
     * @return array<string, mixed>
     */
    protected function headStats($clients, Currency $ils, \Carbon\CarbonImmutable $today): array
    {
        $small = 10; // الرصيد لحد 10 شيكل بينحسب مسدّد (قرار 9/11/2026)
        $live = $clients->where('archived', false);
        $owed = $live->filter(fn ($c) => $c['ils'] > $small);
        $late = $owed->filter(fn ($c) => $c['payDays'] !== null && $c['payDays'] > 30);
        $top = $owed->sortByDesc('ils')->first();

        $m0 = $today->startOfMonth();
        $p0 = $m0->subMonth();
        $sumServices = fn ($from, $to) => (float) \App\Models\ClientService::query()->billable()->where('currency_id', $ils->id)
            ->whereDate('service_date', '>=', $from)->whereDate('service_date', '<=', $to)->sum('amount');
        $sumPayments = fn ($from, $to) => (float) \App\Models\CashPayment::query()->incoming()->active()
            ->where('party_type', 'client')->where('currency_id', $ils->id)
            ->whereDate('occurred_on', '>=', $from)->whereDate('occurred_on', '<=', $to)->sum('amount');
        $svc = $sumServices($m0->toDateString(), $m0->endOfMonth()->toDateString());
        $svcPrev = $sumServices($p0->toDateString(), $p0->endOfMonth()->toDateString());
        $pay = $sumPayments($m0->toDateString(), $m0->endOfMonth()->toDateString());
        $payPrev = $sumPayments($p0->toDateString(), $p0->endOfMonth()->toDateString());
        $pct = fn ($now, $prev) => $prev > 0.005 ? (int) round(($now - $prev) / $prev * 100) : null;
        // زادت/نقصت الديون = اللي عليهم هلأ ناقص اللي كان عليهم أول الشهر (الأدق، اختاره 15/11).
        $owedNow = $owed->sum('ils');
        $owedStart = $live->filter(fn ($c) => $c['open'] > $small)->sum('open');

        return [
            'month' => $today->format('n/Y'),
            'owed' => $owedNow,
            'owedCount' => $owed->count(),
            'late' => $late->sum('ils'),
            'lateCount' => $late->count(),
            'unpaidMonth' => $owed->filter(fn ($c) => ! $c['paidMonth'])->count(),
            'top' => $top ? ['amount' => $top['ils'], 'name' => $top['org'] ?: $top['name']] : null,
            'services' => $svc,
            'servicesPct' => $pct($svc, $svcPrev),
            'payments' => $pay,
            'paymentsPct' => $pct($pay, $payPrev),
            'collect' => $svc > 0.005 ? (int) round($pay / $svc * 100) : null,
            'debtChange' => $owedNow - $owedStart,
            'owedStart' => $owedStart,
        ];
    }

    public function create()
    {
        return view('cp.finance.clients.form', ['client' => new Client(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = true;
        $client = Client::query()->create($data);

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.clients.index', ['flash' => $client->id])->with('success', 'انضاف الزبون.');
        }

        return redirect()->route('cp.clients.show', $client)->with('success', 'انضاف الزبون.');
    }

    public function show(Request $request, Client $client, ClientStatementService $statements)
    {
        // The page filters by type and period in the browser, so it loads the client's whole history once.
        $data = $statements->build($client, null, null);
        $ils = Currency::byCode('ILS');

        $methods = \App\Models\CashPayment::query()
            ->whereIn('id', $data['timeline']->where('type', 'payment')->pluck('id'))
            ->with('paymentMethod')->get()->keyBy('id');
        $takes = $data['goodsTakes']->keyBy('id');
        $memos = $data['balanceMemos']->keyBy('id');
        $services = $client->services->keyBy('id');

        $movements = $data['timeline']->map(function (array $m) use ($methods, $takes, $memos, $services) {
            $row = [
                'id' => $m['type'].'-'.$m['id'],
                'date' => $m['date']->format('Y-m-d'),
                'currency' => $m['currency']->code,
                'symbol' => $m['currency']->symbol,
                'delta' => (float) $m['delta'],
                'amount' => (float) $m['amount'],
                'notes' => (string) ($m['notes'] ?? ''),
            ];

            return $row + match ($m['type']) {
                'service' => [
                    'group' => 'svc',
                    'kind' => $services[$m['id']]?->serviceType?->name ?: $services[$m['id']]?->title ?: 'خدمة',
                    'title' => $services[$m['id']]?->title ?: 'خدمة',
                    'icon' => 'work',
                    'edit' => route('cp.client-services.edit', $m['id']),
                    'delete' => route('cp.client-services.destroy', $m['id']),
                ],
                'payment' => [
                    'group' => 'pay',
                    'kind' => $methods[$m['id']]?->paymentMethod?->name ?: 'دفعة',
                    'title' => 'دفعة · '.($methods[$m['id']]?->paymentMethod?->name ?: ''),
                    'icon' => 'payments',
                    'edit' => route('cp.payments.edit', $m['id']),
                    'delete' => route('cp.payments.destroy', $m['id']),
                ],
                'goods' => [
                    'group' => 'other',
                    'kind' => self::isDiscount($takes[$m['id']] ?? null) ? 'خصم' : 'دفعة بضاعة',
                    'title' => self::isDiscount($takes[$m['id']] ?? null)
                        ? 'خصم'
                        : 'دفعة بضاعة'.($takes[$m['id']]?->title ? ' · '.$takes[$m['id']]->title : ''),
                    'icon' => self::isDiscount($takes[$m['id']] ?? null) ? 'percent' : 'inventory_2',
                    'edit' => route('cp.client-goods-takes.edit', $m['id']),
                    'delete' => route('cp.client-goods-takes.destroy', $m['id']),
                ],
                'memo' => [
                    'group' => 'other',
                    'kind' => 'رصيد سابق',
                    'title' => $m['title'],
                    'icon' => 'history',
                    'edit' => route('cp.balance-memos.edit', $m['id']),
                    'delete' => route('cp.balance-memos.destroy', $m['id']),
                ],
                'refund' => [
                    'group' => 'other',
                    'kind' => 'استرداد',
                    'title' => $m['title'],
                    'icon' => 'undo',
                    'edit' => route('cp.payments.edit', $m['id']),
                    'delete' => route('cp.payments.destroy', $m['id']),
                ],
                default => ['group' => 'other', 'kind' => 'أخرى', 'title' => $m['title'], 'icon' => 'layers', 'edit' => null, 'delete' => null],
            };
        })->values();

        $serviceTypes = \App\Models\ServiceType::query()->where('is_active', true)->with('group')->orderBy('name')
            ->get(['id', 'name', 'service_group_id', 'billing', 'has_phases', 'default_price'])
            ->sortBy(fn ($t) => [$t->group ? 0 : 1, $t->group?->sort_order ?? 0, $t->group?->name ?? '', $t->name])->values();
        $lastPrices = $client->services
            ->sortByDesc(fn ($s) => $s->service_date->format('Y-m-d').sprintf('%010d', $s->id))
            ->groupBy('service_type_id')
            ->map(fn ($rows) => (float) ($rows->first()->source_amount ?: $rows->first()->amount));

        $lookups = [
            'ilsId' => $ils->id,
            'currencies' => Currency::query()->active()->get(['id', 'code', 'symbol', 'name']),
            'serviceTypes' => $serviceTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'group' => $t->group?->name,
                'monthly' => $t->isMonthly(),
                'phases' => (bool) $t->has_phases,
                'price' => $t->default_price !== null ? (float) $t->default_price : null,
                'last' => $lastPrices[$t->id] ?? null,
            ])->values(),
            'methods' => \App\Models\PaymentMethod::query()->active()->get(['id', 'name']),
            'fundId' => (tenantBusinessEnabled() ? \App\Models\Fund::query()->where('slug', 'business')->value('id') : null)
                ?: \App\Models\Fund::family()->id,
            // «السعر العالمي»: سعر الصرف المحفوظ بالنظام (صناديق الأدراج)، قرار 17.
            'rates' => Currency::query()->active()->get(['id', 'code'])
                ->mapWithKeys(fn ($c) => [$c->id => \App\Support\Rates::today()[$c->code] ?? null])->filter(),
        ];

        // اشتراكات الزبون الشغّالة لنافذة «+ خدمة» (قرار 18): الأشهر المنضافة وتاريخها.
        $subToday = \Carbon\CarbonImmutable::today();
        $subscriptions = $client->subscriptions()
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $subToday->toDateString()))
            ->with('months:id,subscription_id,period,service_date')->get()
            ->map(fn (\App\Models\ClientSubscription $s) => [
                'id' => $s->id,
                'type' => $s->service_type_id,
                'title' => $s->title,
                'amount' => (float) $s->amount,
                'from' => $s->starts_on->toDateString(),
                'added' => $s->months->filter(fn ($m) => $m->period)->mapWithKeys(fn ($m) => [$m->period => $m->service_date->toDateString()])->all(),
                'url' => route('cp.subscriptions.month', $s),
            ])->values();

        return view('cp.finance.clients.show', [
            'client' => $client,
            'subscriptions' => $subscriptions,
            'movements' => $movements,
            'lookups' => $lookups,
            'balances' => collect($data['summaries'])->map(fn ($s) => [
                'code' => $s['currency']->code,
                'amount' => (float) $s['lifetime_due'],
                'label' => $s['currency']->format(Money::abs($s['lifetime_due'])),
            ])->filter(fn ($b) => abs($b['amount']) >= 0.005)->values(),
        ]);
    }

    protected static function isDiscount(?\App\Models\ClientGoodsTake $take): bool
    {
        // Goods takes keep their category as the title: the discount preset is stored as «خصم».
        return $take?->title === \App\Enums\GoodsTakeCategory::Discount->label();
    }

    public function exportPdf(Request $request, Client $client, PdfExporter $pdf, ClientStatementService $statements)
    {
        $request->validate([
            'opening' => ['sometimes', 'array'],
            'opening.*' => ['nullable', 'numeric'],
            'type' => ['nullable', 'in:all,svc,pay,other'],
            'kinds' => ['sometimes', 'array'],
            'kinds.*' => ['string', 'max:255'],
        ]);

        $data = $this->showPayload($request, $client, $statements);
        $data['exporting'] = true;

        // Same type filter as the page: services, payments (by method) or other movements.
        $type = $request->input('type', 'all');
        $kinds = array_filter((array) $request->input('kinds', []));
        $groups = ['service' => 'svc', 'payment' => 'pay', 'goods' => 'other', 'memo' => 'other', 'refund' => 'other'];
        // Kinds use the exact labels the client page shows in its filter chips.
        $methods = \App\Models\CashPayment::query()
            ->whereIn('id', $data['timeline']->where('type', 'payment')->pluck('id'))
            ->with('paymentMethod')->get()->keyBy('id');
        $takes = $data['goodsTakes']->keyBy('id');
        $services = $client->services->keyBy('id');
        $kindOf = fn (array $m) => match ($m['type']) {
            'service' => $services[$m['id']]?->serviceType?->name ?: $services[$m['id']]?->title ?: 'خدمة',
            'payment' => $methods[$m['id']]?->paymentMethod?->name ?: 'دفعة',
            'goods' => self::isDiscount($takes[$m['id']] ?? null) ? 'خصم' : 'دفعة بضاعة',
            'memo' => 'رصيد سابق',
            'refund' => 'استرداد',
            default => 'أخرى',
        };
        $data['timeline'] = $data['timeline']->map(fn ($m) => $m + ['kind' => $kindOf($m)]);
        if ($type !== 'all') {
            $data['timeline'] = $data['timeline']
                ->filter(fn ($m) => ($groups[$m['type']] ?? 'other') === $type && (! $kinds || in_array($m['kind'], $kinds, true)))
                ->values();
        }
        $data['filterType'] = $type;
        $data['filterKinds'] = $kinds;
        $data['statementNo'] = 'C'.$client->id.'-'.now()->format('ymd');
        $data['pdfOwnHeader'] = true;
        $data['pdfFooter'] = '<table width="100%" style="font-family:dejavusans;font-size:8px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:4px;"><tr>'
            .'<td width="60%">للاستفسار: م. محمد عمار الشيخ · 0597667671</td>'
            .'<td width="40%" style="text-align:left;">صفحة {PAGENO} من {nbpg}</td></tr></table>';

        return $pdf->download(
            'cp.finance.clients.print',
            $data,
            \App\Support\StatementFilename::make($client->directoryLabel(), $data['from'] ?? null, $data['to'] ?? null, $data['timeline']->pluck('date'))
        );
    }

    public function edit(Client $client)
    {
        return view('cp.finance.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->validated($request));
        if ($request->boolean('subs_sent')) {
            $this->syncSubscriptions($request, $client);
        }

        if ($request->input('return') === 'list') {
            // Enter بخانة المبلغ بيحفظ وبيضل بنفس النافذة (قرار 15).
            $q = ['flash' => $client->id] + ($request->boolean('stay') ? ['edit' => $client->id] : []);

            return redirect()->route('cp.clients.index', $q)->with('success', 'انحفظ التعديل.');
        }
        if ($request->input('return') === 'show') {
            return redirect()->route('cp.clients.show', $client)->with('success', 'انحفظ التعديل.');
        }

        return redirect()->route('cp.clients.show', $client)->with('success', 'انحفظ التعديل.');
    }

    public function destroy(Client $client)
    {
        $client->update(['is_active' => false]);

        return redirect()->route('cp.clients.index')
            ->with('success', 'انأرشف الزبون، وبتلاقيه مع كل حركاته بتاب «مؤرشف».');
    }

    public function restore(Client $client)
    {
        $client->update(['is_active' => true]);

        return redirect()->route('cp.clients.index', ['flash' => $client->id])
            ->with('success', 'رجع الزبون لقائمة الزبائن.');
    }

    /**
     * «إله اشتراك شهري» بتعديل الزبون (قرار 15، 14/11/2026): كل سطر خدمة ومبلغها الشهري، بدون «بيبلّش من».
     * الجديد بيبلّش عدّه من شهر التفعيل. اللي انشال بينحذف، والأشهر اللي انضافت قبل بتضل حركات عادية.
     */
    protected function syncSubscriptions(Request $request, Client $client): void
    {
        $data = $request->validate([
            'subs_on' => ['nullable', 'boolean'],
            'subs' => ['nullable', 'array'],
            'subs.*.type' => ['required', 'exists:service_types,id'],
            'subs.*.amount' => ['nullable', 'numeric', 'gte:0'],
        ]);
        $today = \Carbon\CarbonImmutable::today();
        $wanted = collect($request->boolean('subs_on') ? ($data['subs'] ?? []) : [])
            ->filter(fn ($r) => (float) ($r['amount'] ?? 0) > 0)
            ->keyBy(fn ($r) => (int) $r['type']);
        $active = $client->subscriptions()
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today->toDateString()))
            ->get()->keyBy('service_type_id');
        $ilsId = Currency::byCode('ILS')->id;

        \Illuminate\Support\Facades\DB::transaction(function () use ($wanted, $active, $client, $today, $ilsId) {
            foreach ($active as $typeId => $sub) {
                if (! $wanted->has((int) $typeId)) {
                    $sub->delete();
                }
            }
            foreach ($wanted as $typeId => $row) {
                $amount = Money::of($row['amount']);
                if ($sub = $active->get($typeId)) {
                    if (Money::cmp($sub->amount, $amount) !== 0) {
                        $sub->update(['amount' => $amount]);
                    }
                    continue;
                }
                $type = \App\Models\ServiceType::query()->find($typeId);
                \App\Models\ClientSubscription::query()->create([
                    'client_id' => $client->id,
                    'service_type_id' => $typeId,
                    'title' => $type?->name ?: 'اشتراك',
                    'amount' => $amount,
                    'currency_id' => $ilsId,
                    'starts_on' => $today->startOfMonth()->toDateString(),
                ]);
            }
        });
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => Phone::rules(),
            'notes' => ['nullable', 'string'],
        ], ['phone.regex' => Phone::message()]);
        // The control panel form no longer sends is_active (archiving has its own action); the API still may.
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function showPayload(Request $request, Client $client, ClientStatementService $statements): array
    {
        [$from, $to] = DateRange::fromRequest($request);
        $opening = $request->input('opening', []);

        return $statements->build($client, $from, $to, is_array($opening) ? $opening : []);
    }

    public function unpaidServices(Client $client, Request $request)
    {
        $ils = Currency::byCode('ILS');
        $currencyId = (int) ($request->currency_id ?: $ils->id);
        $currency = Currency::query()->find($currencyId);
        $outstanding = $client->outstandingAmount($currencyId);

        $services = ClientService::query()
            ->billable()
            ->where('client_id', $client->id)
            ->when($currencyId, fn ($q) => $q->where('currency_id', $currencyId))
            ->with(['currency', 'fxCurrency'])
            ->orderByDesc('service_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'amount' => $s->amount,
                'currency_id' => $s->currency_id,
                'currency_code' => $s->currency->code,
                'is_fx' => $s->isFx(),
                'source_amount' => $s->source_amount,
            ]);

        return response()->json([
            'outstanding' => $outstanding,
            'outstanding_formatted' => $currency?->format($outstanding) ?? $outstanding,
            'is_credit' => Money::isNegative($outstanding),
            'credit' => Money::isNegative($outstanding) ? Money::abs($outstanding) : '0.00',
            'credit_formatted' => Money::isNegative($outstanding)
                ? ($currency?->format(Money::abs($outstanding)) ?? Money::abs($outstanding))
                : null,
            'services' => $services,
        ]);
    }
}
