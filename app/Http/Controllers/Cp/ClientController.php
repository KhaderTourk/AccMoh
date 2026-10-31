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

        // Every client (archived included) in one list; filtering and search happen in the browser.
        $clients = Client::query()
            ->orderByRaw("COALESCE(NULLIF(company_name, ''), name)")
            ->get()
            ->map(function (Client $client) use ($currencies) {
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

                return [
                    'id' => $client->id,
                    'org' => (string) $client->organization(),
                    'name' => $client->personName(),
                    'phone' => (string) $client->phone,
                    'notes' => (string) $client->notes,
                    'archived' => ! $client->is_active,
                    'balances' => $balances,
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

        return view('cp.finance.clients.index', compact('clients', 'currencyFormats', 'flash'));
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
                default => ['group' => 'other', 'kind' => 'أخرى', 'title' => $m['title'], 'icon' => 'layers', 'edit' => null, 'delete' => null],
            };
        })->values();

        $serviceTypes = \App\Models\ServiceType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $lastPrices = $client->services
            ->sortByDesc(fn ($s) => $s->service_date->format('Y-m-d').sprintf('%010d', $s->id))
            ->groupBy('service_type_id')
            ->map(fn ($rows) => (float) ($rows->first()->source_amount ?: $rows->first()->amount));

        $lookups = [
            'ilsId' => $ils->id,
            'currencies' => Currency::query()->active()->get(['id', 'code', 'symbol', 'name']),
            'serviceTypes' => $serviceTypes->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'last' => $lastPrices[$t->id] ?? null])->values(),
            'methods' => \App\Models\PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'fundId' => (tenantBusinessEnabled() ? \App\Models\Fund::query()->where('slug', 'business')->value('id') : null)
                ?: \App\Models\Fund::family()->id,
            'rates' => \App\Models\CashPayment::query()->whereNotNull('fx_currency_id')->whereNotNull('exchange_rate')
                ->latest('occurred_on')->get(['fx_currency_id', 'exchange_rate'])->unique('fx_currency_id')
                ->mapWithKeys(fn ($p) => [$p->fx_currency_id => (float) $p->exchange_rate]),
        ];

        return view('cp.finance.clients.show', [
            'client' => $client,
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
        ]);

        $data = $this->showPayload($request, $client, $statements);
        $data['exporting'] = true;

        $suffix = ($data['from'] || $data['to'])
            ? '-'.($data['from'] ?: 'start').'-'.($data['to'] ?: 'now')
            : '';

        return $pdf->download(
            'cp.finance.clients.print',
            $data,
            'client-'.$client->id.$suffix.'.pdf'
        );
    }

    public function edit(Client $client)
    {
        return view('cp.finance.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->validated($request));

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.clients.index', ['flash' => $client->id])->with('success', 'انحفظ التعديل.');
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
