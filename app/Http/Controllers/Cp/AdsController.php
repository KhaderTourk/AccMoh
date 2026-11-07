<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\MetaInvoice;
use App\Models\PaymentMethod;
use App\Services\Finance\AdsService;
use App\Support\Rates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** الإعلانات الممولة (تسليم 1/11 §1): حملات · فواتير ميتا · المطابقة. */
class AdsController extends Controller
{
    public function index(Request $request, AdsService $ads)
    {
        $tab = $request->query('tab') === 'invoices' ? 'invoices' : 'campaigns';
        $rec = $ads->reconciliation();
        $profit = $ads->profit();
        $approved = collect($rec)->filter(fn ($r) => $r['month']?->isApproved())->keys()->all();
        $usd = Currency::byCode('USD');
        $lastInvoiceRate = (float) (MetaInvoice::query()->latest('invoice_date')->latest('id')->value('usd_rate') ?? 0);
        $rate = $lastInvoiceRate ?: (float) (Rates::today()['USD'] ?? 0);
        $campaigns = $ads->campaigns()->map(function (AdCampaign $c) use ($approved) {
            return [
                'id' => $c->id, 'client_id' => $c->client_id,
                'name' => $c->client?->directoryLabel() ?? 'زبون محذوف',
                'period' => $c->period, 'fb' => $c->on_facebook, 'ig' => $c->on_instagram,
                'spend' => (float) $c->spend_usd, 'sell' => (float) $c->sell_rate, 'notes' => $c->notes,
                'approved' => in_array($c->period, $approved, true),
                'url' => $c->client ? route('cp.clients.show', $c->client) : null,
            ];
        })->values();
        // سعر تكلفة الدولار لكل شهر (الفواتير بتسدّ الأقدم أول)، والشهر اللي لسا ما انسد ربحه تقديري
        $monthCost = [];
        foreach ($profit as $p => $row) {
            $spend = $campaigns->where('period', $p)->sum('spend');
            $monthCost[$p] = ['rate' => $spend > 0 ? round($row['cost'] / $spend, 6) : $rate, 'confirmed' => $row['confirmed']];
        }
        $openMonths = collect($rec)->reject(fn ($r) => $r['month']?->isApproved())->keys()->values();
        $periods = $campaigns->pluck('period')->unique()->sortDesc()->values();
        $thisMonth = now()->format('Y-m');
        $methods = PaymentMethod::query()->where('is_active', true)->orderBy('id')->get();
        $meta = $ads->metaVendor();

        return view('cp.finance.ads.index', [
            'tab' => $tab,
            'campaigns' => $campaigns,
            'periods' => $periods,
            'defaultMonths' => $periods->contains($thisMonth) ? [$thisMonth] : [],
            'monthCost' => $monthCost,
            'rate' => $rate,
            'clients' => Client::query()->active()->get()->sortBy(fn ($c) => $c->directoryLabel())->values()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->directoryLabel()]),
            'lastRates' => $ads->campaigns()->sortByDesc('period')->unique('client_id')->mapWithKeys(fn ($c) => [$c->client_id => (float) $c->sell_rate]),
            'rec' => collect($rec)->map(fn ($r) => array_merge($r, ['month' => null, 'approved' => (bool) $r['month']?->isApproved()]))->all(),
            'openMonths' => $openMonths,
            'invoices' => MetaInvoice::query()->with('paymentMethod')->orderByDesc('invoice_date')->orderByDesc('id')->get(),
            'metaBalance' => round((float) $meta->outstandingAmount($usd->id), 2),
            'limit' => $ads->diffLimit(),
            'methods' => $methods,
            'monthOpts' => collect(range(-12, 1))->map(fn ($i) => now()->startOfMonth()->addMonthsNoOverflow($i)->format('Y-m'))->merge($periods)->unique()->sortDesc()->values(),
            'thisMonth' => $thisMonth,
        ]);
    }

    public function storeCampaign(Request $request, AdsService $ads)
    {
        return $this->saveCampaign($request, $ads, new AdCampaign);
    }

    public function updateCampaign(Request $request, AdsService $ads, AdCampaign $campaign)
    {
        return $this->saveCampaign($request, $ads, $campaign);
    }

    public function destroyCampaign(AdsService $ads, AdCampaign $campaign)
    {
        $reopened = DB::transaction(function () use ($ads, $campaign) {
            $r = $ads->reopen($campaign->period);
            $campaign->delete();

            return $r;
        });

        return back()->with('success', $reopened ? 'انحذفت الحملة. الشهر رجع مسودة، اعتمده من جديد.' : 'انحذفت الحملة.');
    }

    protected function saveCampaign(Request $request, AdsService $ads, AdCampaign $campaign)
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'new_client' => ['nullable', 'string', 'max:255'],
            'new_company' => ['nullable', 'string', 'max:255'],
            'new_phone' => ['nullable', 'regex:/^05\\d{8}$/'],
            'new_notes' => ['nullable', 'string'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'on_facebook' => ['nullable', 'boolean'],
            'on_instagram' => ['nullable', 'boolean'],
            'spend_usd' => ['required', 'numeric', 'gt:0'],
            'sell_rate' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if (! $request->boolean('on_facebook') && ! $request->boolean('on_instagram')) {
            return back()->withInput()->withErrors(['on_facebook' => 'اختار فيس أو انستا أو الثنتين.']);
        }
        try {
            $reopened = DB::transaction(function () use ($request, $ads, $campaign, $data) {
            $clientId = $data['client_id'] ?? null;
            if (filled($data['new_client'] ?? null)) {
                $clientId = Client::query()->create(['name' => trim($data['new_client']), 'company_name' => $data['new_company'] ?? null,
                    'phone' => $data['new_phone'] ?? null, 'notes' => $data['new_notes'] ?? null, 'is_active' => true])->id;
            }
            if (! $clientId || ! Client::query()->whereKey($clientId)->exists()) {
                throw new FinanceException('اختار الزبون.');
            }
            $r = false;
            if ($campaign->exists) {
                $r = $ads->reopen($campaign->period);
            }
            $r = $ads->reopen($data['period']) || $r;
            $campaign->fill([
                'client_id' => $clientId, 'period' => $data['period'], 'on_facebook' => $request->boolean('on_facebook'),
                'on_instagram' => $request->boolean('on_instagram'), 'spend_usd' => $data['spend_usd'], 'sell_rate' => $data['sell_rate'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            return $r;
            });
        } catch (FinanceException $e) {
            return back()->withInput()->withErrors(['ads' => $e->getMessage()]);
        }

        return redirect()->route('cp.ads.index', ['tab' => 'campaigns'])
            ->with('success', $reopened ? 'انحفظت الحملة. الشهر كان معتمد ورجع مسودة، اعتمده من جديد.' : 'انحفظت الحملة.');
    }

    public function storeInvoice(Request $request, AdsService $ads)
    {
        $data = $request->validate([
            'invoice_date' => ['required', 'date'],
            'amount_usd' => ['required', 'numeric', 'gt:0'],
            'meta_ref' => ['required', 'string', 'max:100'],
            'source' => ['required', 'regex:/^\\d+:(ILS|USD)$/'],
            'usd_rate' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        [$data['payment_method_id'], $data['paid_in']] = explode(':', $data['source']);
        $data['fund_id'] = Fund::business()->id;
        try {
            $ads->recordInvoice($data);
        } catch (FinanceException $e) {
            return back()->withInput()->withErrors(['ads' => $e->getMessage()]);
        }

        return redirect()->route('cp.ads.index', ['tab' => 'invoices'])->with('success', 'انسجلت الفاتورة.');
    }

    public function approve(Request $request, AdsService $ads, string $period)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $period), 404);
        $data = $request->validate(['meta_balance' => ['required', 'numeric']]);
        try {
            $ads->approve($period, (float) $data['meta_balance'], $request->boolean('skip'));
        } catch (FinanceException $e) {
            return back()->withInput()->withErrors(['ads' => $e->getMessage()])->with('adsPeriod', $period);
        }

        return redirect()->route('cp.ads.index')->with('success', $request->boolean('skip') ? 'انعتمد شهر '.$period.' مع تخطي الفرق، وانسجلت تسوية.' : 'انعتمد شهر '.$period.' ونزلت المبالغ على كشوف الزباين.');
    }

    public function limit(Request $request)
    {
        $data = $request->validate(['limit' => ['required', 'numeric', 'min:0']]);
        AppSetting::put('ads.diff_limit', (string) $data['limit']);

        return redirect()->route('cp.ads.index')->with('success', 'انحفظ حد الفرق.');
    }
}
