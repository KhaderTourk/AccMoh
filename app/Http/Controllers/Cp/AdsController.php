<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Fund;
use App\Models\MetaInvoice;
use App\Models\PaymentMethod;
use App\Services\Finance\AdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** الإعلانات الممولة (تسليم 1/11 §1): حملات · فواتير ميتا · المطابقة. */
class AdsController extends Controller
{
    public function index(Request $request, AdsService $ads)
    {
        $tab = in_array($request->query('tab'), ['campaigns', 'invoices', 'match'], true) ? $request->query('tab') : 'campaigns';
        $campaigns = $ads->campaigns();
        $periods = $ads->periods();
        $months = array_filter((array) $request->query('m', []), fn ($p) => is_string($p) && preg_match('/^\d{4}-\d{2}$/', $p));
        $platform = in_array($request->query('p'), ['fb', 'ig'], true) ? $request->query('p') : null;
        $shown = $campaigns
            ->when($months, fn ($c) => $c->whereIn('period', $months))
            ->when($platform === 'fb', fn ($c) => $c->where('on_facebook', true))
            ->when($platform === 'ig', fn ($c) => $c->where('on_instagram', true));
        $groups = $shown->groupBy('client_id')->sortBy(fn ($g) => $g->first()->client?->directoryLabel());
        $clients = Client::query()->active()->get()->sortBy(fn ($c) => $c->directoryLabel())->values();
        $lastRates = $campaigns->sortByDesc('period')->unique('client_id')->mapWithKeys(fn ($c) => [$c->client_id => (float) $c->sell_rate]);

        return view('cp.finance.ads.index', [
            'tab' => $tab,
            'groups' => $groups,
            'periods' => $periods,
            'months' => array_values($months),
            'platform' => $platform,
            'clients' => $clients,
            'lastRates' => $lastRates,
            'approved' => collect($ads->reconciliation())->filter(fn ($r) => $r['month']?->isApproved())->keys()->all(),
            'invoices' => MetaInvoice::query()->with(['fund', 'paymentMethod'])->orderByDesc('invoice_date')->orderByDesc('id')->get(),
            'match' => array_reverse($ads->reconciliation(), true),
            'profit' => $ads->profit(),
            'limit' => $ads->diffLimit(),
            'funds' => Fund::query()->orderBy('id')->get(),
            'methods' => PaymentMethod::query()->where('is_active', true)->orderBy('id')->get(),
            'thisMonth' => now()->format('Y-m'),
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
                $clientId = Client::query()->create(['name' => trim($data['new_client']), 'is_active' => true])->id;
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
            'meta_ref' => ['nullable', 'string', 'max:100'],
            'fund_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'paid_in' => ['required', 'in:ILS,USD'],
            'usd_rate' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
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

        return redirect()->route('cp.ads.index', ['tab' => 'match'])->with('success', 'انعتمد الشهر.');
    }

    public function limit(Request $request)
    {
        $data = $request->validate(['limit' => ['required', 'numeric', 'min:0']]);
        AppSetting::put('ads.diff_limit', (string) $data['limit']);

        return redirect()->route('cp.ads.index', ['tab' => 'match'])->with('success', 'انحفظ حد الفرق.');
    }
}
