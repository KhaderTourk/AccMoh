<?php

namespace App\Http\Controllers\Cp;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\Vendor;
use App\Services\Export\PdfExporter;
use App\Services\Finance\PartyPageService;
use App\Support\DateRange;
use App\Support\PartyStatement;
use App\Support\Phone;
use App\Support\TableSort;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $type = $this->type();
        $currencies = Currency::query()->active()->get();
        $prefix = $type->routePrefix();

        $pages = app(PartyPageService::class);
        if ($type === VendorType::Worker) {
            return $this->workersIndex($request, $pages);
        }
        $rows = Vendor::query()->ofType($type)->with('subscriptions.months:id,subscription_id,period')->orderBy('name')->get()
            ->map(fn (Vendor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sub' => (string) ($type === VendorType::Worker ? $v->job_title : $v->work_description),
                'phone' => (string) $v->phone,
                'notes' => (string) $v->notes,
                'archived' => ! $v->is_active,
                // فلتر «الرواتب الشهرية» / «الخدمات الشهرية» (قرار 10/11/2026)
                'monthly' => VendorSubscriptionController::plans($v),
                // موجب = عليه (لصالحنا)، سالب = إله، زي باقي القوائم
                'balances' => $this->balances($currencies, fn ($id) => \App\Support\Money::neg($v->outstandingAmount($id))),
                'pinned' => (bool) $v->is_pinned,
                'pin' => route('cp.pin.toggle', ['kind' => 'vendor', 'id' => $v->id]),
                ...\App\Support\PartyActivity::fromMovements($pages->movements($v), true),
                'fields' => [
                    'detail' => (string) ($type === VendorType::Worker ? $v->job_title : $v->work_description),
                ],
                'show' => route("cp.{$prefix}.show", $v),
                'pdf' => route("cp.{$prefix}.export-pdf", $v),
                'update' => route("cp.{$prefix}.update", $v),
                'archive' => route("cp.{$prefix}.destroy", $v),
                'restore' => route("cp.{$prefix}.restore", $v),
            ])->values();

        return view('cp.finance.parties.index', [
            'party' => $this->profile(),
            'rows' => $rows,
            'flash' => (int) $request->query('flash'),
            'storeUrl' => route("cp.{$prefix}.store"),
        ]);
    }

    public function create()
    {
        $type = $this->type();

        return view('cp.finance.vendors.form', [
            'vendor' => new Vendor(['is_active' => true, 'type' => $type]),
            'type' => $type,
        ]);
    }

    public function store(Request $request)
    {
        $type = $this->type();
        $vendor = Vendor::query()->create($this->validated($request) + ['type' => $type->value, 'is_active' => true]);

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.'.$type->routePrefix().'.index', ['flash' => $vendor->id])->with('success', 'تم الإضافة.');
        }

        return redirect()->route('cp.'.$type->routePrefix().'.show', $vendor)
            ->with('success', 'تم الإضافة.');
    }

    public function show(Vendor $vendor)
    {
        $pages = app(PartyPageService::class);
        $this->assertType($vendor);

        return view('cp.finance.parties.show', $this->pagePayload($vendor, $pages));
    }

    public function exportPdf(Request $request, Vendor $vendor, PdfExporter $pdf)
    {
        $pages = app(PartyPageService::class);
        $this->assertType($vendor);

        $data = PartyStatement::data($request, $this->profile(), $vendor->name, $this->subtitle($vendor), $pages->movements($vendor));

        return $pdf->download(
            'cp.finance.parties.print',
            $data,
            \App\Support\StatementFilename::make($vendor->name, $data['from'], $data['to'], array_column($data['rows'], 'date'))
        );
    }

    public function edit(Vendor $vendor)
    {
        $this->assertType($vendor);

        return view('cp.finance.vendors.form', [
            'vendor' => $vendor,
            'type' => $this->type(),
        ]);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $this->assertType($vendor);
        $vendor->update($this->validated($request));
        if ($vendor->type === VendorType::Worker && $request->boolean('subs_sent')) {
            $this->syncSalaries($request, $vendor);
        }
        if ($vendor->type === VendorType::Worker && $request->input('return') === 'list') {
            // Enter بخانة المبلغ بيحفظ وبيضل بنفس النافذة (زي الزبائن، قرار 15).
            $q = ['flash' => $vendor->id] + ($request->boolean('stay') ? ['edit' => $vendor->id] + ($request->input('panel') === 'subs' ? ['panel' => 'subs'] : []) : []);

            return redirect()->route('cp.workers.index', $q)->with('success', 'انحفظ التعديل.');
        }

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.'.$this->type()->routePrefix().'.index', ['flash' => $vendor->id])->with('success', 'انحفظ التعديل.');
        }

        return redirect()->route('cp.'.$this->type()->routePrefix().'.show', $vendor)
            ->with('success', 'تم التحديث.');
    }

    public function destroy(Vendor $vendor)
    {
        $this->assertType($vendor);
        $type = $this->type();

        $vendor->update(['is_active' => false]);

        return redirect()->route('cp.'.$type->routePrefix().'.index')
            ->with('success', 'انأرشف، وبتلاقيه مع كل حركاته بتاب «مؤرشف».');
    }

    public function restore(Vendor $vendor)
    {
        $this->assertType($vendor);
        $vendor->update(['is_active' => true]);

        return redirect()->route('cp.'.$this->type()->routePrefix().'.index', ['flash' => $vendor->id])
            ->with('success', 'رجع للقائمة.');
    }

    /**
     * شاشة الموظفين على قالب الزبائن (15/11/2026، المرحلتين 1 و2 معتمدات): جدول بيترتّب من راسه، تابات، بحث بالاقتراحات،
     * «راتب شهري» من ⋯، وكرتين فوق. الرصيد الموجب = إله (أخضر)، السالب = عليه سلفة (وردي).
     */
    protected function workersIndex(Request $request, PartyPageService $pages)
    {
        $ils = Currency::byCode('ILS');
        $today = \Carbon\CarbonImmutable::today();
        $monthStart = $today->startOfMonth()->toDateString();
        $lastPeriod = $today->subMonthNoOverflow()->format('Y-m');
        \App\Models\VendorChargeType::seedDefaults();

        $workers = Vendor::query()->ofType(VendorType::Worker)->with('subscriptions.months:id,subscription_id,period')->orderBy('name')->get()
            ->map(function (Vendor $v) use ($pages, $ils, $today, $monthStart, $lastPeriod) {
                $moves = $pages->movements($v);
                $ilsMoves = $moves->where('currency', $ils->code);
                $activity = \App\Support\PartyActivity::fromMovements($moves, true);
                $since = $activity['last_pay'] ?: $activity['first_move'];
                $active = $v->subscriptions->filter(fn ($s) => ! $s->ends_on || $s->ends_on->toDateString() >= $today->toDateString());
                // «متابعة»: راتب الشهر الماضي لسا ما انضاف (بس للرواتب اللي بتغطي الشهر الماضي ومن بعد بداية التتبّع).
                $missLast = $v->subscriptions->contains(fn ($s) => $s->isBillable($lastPeriod, $today) && in_array($lastPeriod, $s->pendingMonths($lastPeriod), true));

                return [
                    'id' => $v->id,
                    'name' => $v->name,
                    'job' => (string) $v->job_title,
                    'phone' => (string) $v->phone,
                    'notes' => (string) $v->notes,
                    'archived' => ! $v->is_active,
                    'subs' => $active->sortBy('id')->map(fn ($s) => [
                        'id' => $s->id, 'type' => $s->charge_type_id, 'title' => $s->title, 'amount' => (float) $s->amount, 'months' => $s->months->count(),
                    ])->values()->all(),
                    'ils' => round((float) $ilsMoves->sum('delta'), 2),
                    'open' => round((float) $ilsMoves->filter(fn ($m) => $m['date'] < $monthStart)->sum('delta'), 2),
                    'payDays' => $since ? (int) \Carbon\CarbonImmutable::parse($since)->diffInDays($today) : null,
                    'missLast' => $missLast,
                    'dueMonth' => round((float) $ilsMoves->filter(fn ($m) => $m['group'] === 'g1' && $m['date'] >= $monthStart)->sum('delta'), 2),
                    'show' => route('cp.workers.show', $v),
                    'pdf' => route('cp.workers.export-pdf', $v),
                    'update' => route('cp.workers.update', $v),
                    'archive' => route('cp.workers.destroy', $v),
                    'restore' => route('cp.workers.restore', $v),
                ];
            })->values();

        return view('cp.finance.vendors.workers', [
            'workers' => $workers,
            'head' => $this->workersHead($workers, $ils, $today),
            'types' => \App\Models\VendorChargeType::query()->for(VendorType::Worker)->where('is_active', true)->get(['id', 'name'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'flash' => (int) $request->query('flash'),
            'lastLabel' => \App\Support\MonthlyPlan::label($lastPeriod),
        ]);
    }

    /**
     * كرتين رأس شاشة الموظفين (المرحلة 2، 15/11/2026): «علي» و«هالشهر»، كل الأرقام حسب تاريخ الحركة نفسها.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $workers
     * @return array<string, mixed>
     */
    protected function workersHead($workers, Currency $ils, \Carbon\CarbonImmutable $today): array
    {
        $small = 10;
        $live = $workers->where('archived', false);
        $owed = $live->filter(fn ($w) => $w['ils'] > $small);
        $late = $owed->filter(fn ($w) => $w['payDays'] !== null && $w['payDays'] > 30);
        $miss = $live->filter(fn ($w) => $w['missLast']);
        $top = $owed->sortByDesc('ils')->first();
        $m0 = $today->startOfMonth();
        $p0 = $m0->subMonth();
        $ids = $live->pluck('id');
        $sumDues = fn ($from, $to) => (float) \App\Models\VendorCharge::query()->whereIn('vendor_id', $ids)->where('currency_id', $ils->id)
            ->whereDate('charge_date', '>=', $from)->whereDate('charge_date', '<=', $to)->sum('amount');
        $sumPaid = fn ($from, $to) => (float) \App\Models\CashPayment::query()->outgoing()->active()
            ->where('party_type', (new Vendor)->getMorphClass())->whereIn('party_id', $ids)->where('currency_id', $ils->id)
            ->whereDate('occurred_on', '>=', $from)->whereDate('occurred_on', '<=', $to)->sum('amount');
        $dues = $sumDues($m0->toDateString(), $m0->endOfMonth()->toDateString());
        $duesPrev = $sumDues($p0->toDateString(), $p0->endOfMonth()->toDateString());
        $paid = $sumPaid($m0->toDateString(), $m0->endOfMonth()->toDateString());
        $paidPrev = $sumPaid($p0->toDateString(), $p0->endOfMonth()->toDateString());
        $pct = fn ($now, $prev) => $prev > 0.005 ? (int) round(($now - $prev) / $prev * 100) : null;
        $owedNow = $owed->sum('ils');
        $owedStart = $live->filter(fn ($w) => $w['open'] > $small)->sum('open');

        return [
            'month' => $today->format('n/Y'),
            'owed' => $owedNow,
            'owedCount' => $owed->count(),
            'late' => $late->sum('ils'),
            'lateCount' => $late->count(),
            'lateName' => $late->count() === 1 ? $late->first()['name'] : null,
            'miss' => $miss->sum(fn ($w) => collect($w['subs'])->sum('amount')),
            'missCount' => $miss->count(),
            'missName' => $miss->count() === 1 ? $miss->first()['name'] : null,
            'top' => $top ? ['amount' => $top['ils'], 'name' => $top['name']] : null,
            'dues' => $dues,
            'duesPct' => $pct($dues, $duesPrev),
            'paid' => $paid,
            'paidPct' => $pct($paid, $paidPrev),
            'ratio' => $dues > 0.005 ? (int) round($paid / $dues * 100) : null,
            'change' => $owedNow - $owedStart,
            'owedStart' => $owedStart,
        ];
    }

    /**
     * «راتب شهري» من ⋯ بقائمة الموظفين (المرحلة 1): كل سطر نوع من «مستحقات الموظفين» ومبلغه الشهري.
     * الجديد بيبلّش من شهر التفعيل. اللي انشال: إذا إله شهور منضافة بيوقف اليوم وبتضل حركاته، وإلا بينحذف.
     */
    protected function syncSalaries(Request $request, Vendor $vendor): void
    {
        $data = $request->validate([
            'subs_on' => ['nullable', 'boolean'],
            'subs' => ['nullable', 'array'],
            'subs.*.type' => ['required', 'exists:vendor_charge_types,id'],
            'subs.*.amount' => ['nullable', 'numeric', 'gte:0'],
        ]);
        $today = \Carbon\CarbonImmutable::today();
        $wanted = collect($request->boolean('subs_on') ? ($data['subs'] ?? []) : [])
            ->filter(fn ($r) => (float) ($r['amount'] ?? 0) > 0)
            ->keyBy(fn ($r) => (int) $r['type']);
        $active = $vendor->subscriptions()
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today->toDateString()))
            ->get()->keyBy('charge_type_id');
        $ilsId = Currency::byCode('ILS')->id;

        \Illuminate\Support\Facades\DB::transaction(function () use ($wanted, $active, $vendor, $today, $ilsId) {
            foreach ($active as $typeId => $sub) {
                if ($wanted->has((int) $typeId)) {
                    continue;
                }
                if ($sub->months()->exists()) {
                    $sub->update(['ends_on' => max($today->toDateString(), $sub->starts_on->toDateString())]);
                } else {
                    $sub->delete();
                }
            }
            foreach ($wanted as $typeId => $row) {
                $amount = \App\Support\Money::of($row['amount']);
                if ($sub = $active->get($typeId)) {
                    if (\App\Support\Money::cmp($sub->amount, $amount) !== 0) {
                        $sub->update(['amount' => $amount]);
                    }
                    continue;
                }
                $type = \App\Models\VendorChargeType::query()->find($typeId);
                \App\Models\VendorSubscription::query()->create([
                    'vendor_id' => $vendor->id,
                    'charge_type_id' => $typeId,
                    'title' => $type?->name ?: 'راتب',
                    'amount' => $amount,
                    'currency_id' => $ilsId,
                    'starts_on' => $today->startOfMonth()->toDateString(),
                ]);
            }
        });
    }

    protected function type(): VendorType
    {
        $name = (string) request()->route()?->getName();

        return str_contains($name, 'workers') ? VendorType::Worker : VendorType::Supplier;
    }

    protected function assertType(Vendor $vendor): void
    {
        abort_unless($vendor->type === $this->type(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => Phone::rules(),
            'notes' => ['nullable', 'string'],
        ];
        // الراتب/الاشتراك الشهري صار من «مستحق» ← «شهري» بصفحة الحساب (قرار 11/11/2026)، مش من هون.
        if ($this->type() === VendorType::Worker) {
            $rules['job_title'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['work_description'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules, ['phone.regex' => Phone::message()]);
        if ($request->has('detail')) {
            $data[$this->type() === VendorType::Worker ? 'job_title' : 'work_description'] = $request->input('detail');
        }
        // The list window has no archive switch; only the old form sends it.
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }

    /**
     * Payload the API's vendor endpoint still reads.
     *
     * @return array<string, mixed>
     */
    protected function showPayload(Vendor $vendor, $currencies): array
    {
        $type = $this->type();

        return [
            'vendor' => $vendor,
            'currencies' => $currencies,
            'type' => $type,
            'businessFundId' => Fund::business()->id,
            'exportedAt' => format_date(now(), true),
            'title' => $vendor->name,
            'subtitle' => trim(implode(' · ', array_filter([$type->label(), $vendor->job_title ?: $vendor->work_description, $vendor->phone]))),
        ];
    }

    /** @return array<string, mixed> */
    protected function profile(): array
    {
        $worker = $this->type() === VendorType::Worker;

        return [
            'kind' => $worker ? 'worker' : 'supplier',
            'noun' => $worker ? 'موظف' : 'مورد',
            'plural' => $worker ? 'الموظفين' : 'الموردين',
            'pos' => 'إله', 'neg' => 'عليه',
            'g1' => $worker ? 'مستحقات' : 'خدمات', 'g2' => 'دفعات',
            'detail' => $worker ? 'المسمّى الوظيفي' : 'شو بيورّد',
            'legal' => true,
        ];
    }

    protected function subtitle(Vendor $vendor): string
    {
        return trim(implode(' · ', array_filter([$vendor->job_title ?: $vendor->work_description, $vendor->phone])));
    }

    /** @return array<string, mixed> */
    protected function pagePayload(Vendor $vendor, PartyPageService $pages): array
    {
        $prefix = $this->type()->routePrefix();
        $methods = \App\Models\PaymentMethod::query()->active()->get(['id', 'name']);
        if ($this->type() === VendorType::Worker) {
            \App\Models\VendorChargeType::seedDefaults();
        }
        // «آخر مرة لهالموظف» لكل نوع، بالشيكل (نموذج المبلغ، قرار 17).
        $lastAmounts = $vendor->charges()->whereNotNull('charge_type_id')->orderByDesc('charge_date')->orderByDesc('id')
            ->get(['id', 'charge_type_id', 'amount'])->unique('charge_type_id')
            ->mapWithKeys(fn ($c) => [$c->charge_type_id => (float) $c->amount])->all();

        return [
            'party' => $this->profile(),
            'model' => $vendor,
            'title' => $vendor->name,
            'subtitle' => $this->subtitle($vendor),
            'movements' => $pages->movements($vendor),
            'lookups' => [
                'currencies' => Currency::query()->active()->get(['id', 'code', 'symbol', 'name']),
                'methods' => $methods,
                'fundId' => Fund::business()->id,
                'dueTypes' => \App\Models\VendorChargeType::query()->for($this->type())->where('is_active', true)->get(['id', 'name'])
                    ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'last' => $lastAmounts[$t->id] ?? null])->values(),
                'ilsId' => Currency::byCode('ILS')->id,
                'rates' => Currency::query()->active()->get(['id', 'code'])
                    ->mapWithKeys(fn ($c) => [$c->id => \App\Support\Rates::today()[$c->code] ?? null])->filter(),
                'manageTypes' => route('cp.settings.index', ['tab' => $this->type() === VendorType::Worker ? 'worker-dues' : 'supplier-dues']),
                'partyKey' => 'vendor:'.$vendor->id,
                'targets' => [],
            ],
            'subscriptions' => VendorSubscriptionController::forPage($vendor),
            'urls' => [
                'charge' => route('cp.vendor-charges.store'),
                'sub' => route('cp.vendor-subscriptions.store'),
                'payment' => route('cp.payments.store', ['outgoing']),
                'goods' => route('cp.client-goods-takes.store'),
                'memo' => route('cp.balance-memos.store'),
                'pdf' => route("cp.{$prefix}.export-pdf", $vendor),
                'index' => route("cp.{$prefix}.index"),
                'edit' => $this->type() === VendorType::Worker ? route('cp.workers.index', ['edit' => $vendor->id, 'back' => 'show']) : route("cp.{$prefix}.edit", $vendor),
                'archive' => $vendor->is_active ? route("cp.{$prefix}.destroy", $vendor) : route("cp.{$prefix}.restore", $vendor),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function balances($currencies, callable $amount): array
    {
        $out = [];
        foreach ($currencies as $currency) {
            $v = $amount($currency->id);
            if (! \App\Support\Money::isZero($v)) {
                $out[] = ['code' => $currency->code, 'amount' => (float) $v, 'label' => $currency->format(\App\Support\Money::abs($v))];
            }
        }

        return $out;
    }
}
