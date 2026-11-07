<?php

namespace App\Services\Finance;

use App\Enums\VendorType;
use App\Exceptions\FinanceException;
use App\Models\AdCampaign;
use App\Models\AdMonth;
use App\Models\AppSetting;
use App\Models\ClientGoodsTake;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\MetaInvoice;
use App\Models\ServiceType;
use App\Models\Vendor;
use App\Models\VendorCharge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * الإعلانات الممولة (تسليم 1/11 §1).
 *
 * - حساب ميتا مورد بالدولار: اعتماد الشهر بيسجّل عليه دين بمجموع الصرف، وكل فاتورة بتسدّه.
 * - الفاتورة بتنخصم من درج بالشيكل: بنسجّل صرف عملة (شيكل ← دولار بسعر يومها) بنفس الدرج، وبعدها دفعة بالدولار لميتا.
 * - المطابقة: ضايل من الشهر الماضي + حملات الشهر − الفواتير المدفوعة فيه = المفروض يترحّل.
 * - الربح: الفواتير بتسدّ الأقدم أول (FIFO)، والشهر بيتأكد ربحه لما ينسد كله.
 */
class AdsService
{
    public function __construct(
        protected ClientWorkService $work,
        protected VendorChargeService $charges,
        protected ClientGoodsTakeService $takes,
        protected CashPaymentService $payments,
        protected FundTransferService $transfers,
    ) {}

    public function metaVendor(): Vendor
    {
        return Vendor::query()->where('name', 'ميتا')->where('type', VendorType::Supplier)->first()
            ?? Vendor::query()->create(['name' => 'ميتا', 'type' => VendorType::Supplier, 'work_description' => 'إعلانات فيسبوك وانستغرام', 'is_active' => true]);
    }

    public function serviceType(): ServiceType
    {
        // «إعلان ممول» ما بتطلع بقائمة خدمات الزبون عشان ما تنسجل مرتين: بتنضاف من التاب بس
        return ServiceType::query()->where('name', 'إعلان ممول')->first()
            ?? ServiceType::query()->create(['name' => 'إعلان ممول', 'billing' => 'once', 'is_active' => false]);
    }

    public function diffLimit(): float
    {
        return (float) AppSetting::get('ads.diff_limit', '2');
    }

    public function lastSellRate(int $clientId): float
    {
        return (float) (AdCampaign::query()->where('client_id', $clientId)->latest('period')->latest('id')->value('sell_rate') ?? 5);
    }

    public function month(string $period): AdMonth
    {
        return AdMonth::query()->firstOrCreate(['period' => $period], ['status' => 'draft']);
    }

    public function isApproved(string $period): bool
    {
        return AdMonth::query()->where('period', $period)->where('status', 'approved')->exists();
    }

    /** @return list<string> كل الشهور اللي فيها حملات أو فواتير، من الأقدم */
    public function periods(): array
    {
        return AdCampaign::query()->distinct()->pluck('period')
            ->merge(MetaInvoice::query()->get(['invoice_date'])->map(fn ($i) => $i->invoice_date->format('Y-m')))
            ->unique()->sort()->values()->all();
    }

    /**
     * المطابقة لكل شهر: ضايل من الماضي، حملات، فواتير، والمفروض يترحّل (بالدولار).
     *
     * @return array<string, array{prev: float, spend: float, paid: float, expected: float, carry: float, month: ?AdMonth}>
     */
    public function reconciliation(): array
    {
        $spend = AdCampaign::query()->selectRaw('period, SUM(spend_usd) as s')->groupBy('period')->pluck('s', 'period');
        $paid = [];
        foreach (MetaInvoice::query()->get(['invoice_date', 'amount_usd']) as $i) {
            $k = $i->invoice_date->format('Y-m');
            $paid[$k] = ($paid[$k] ?? 0) + (float) $i->amount_usd;
        }
        $months = AdMonth::query()->get()->keyBy('period');
        $out = [];
        $carry = 0.0;
        foreach ($this->periods() as $p) {
            $prev = $carry;
            $expected = round($prev + (float) ($spend[$p] ?? 0) - ($paid[$p] ?? 0), 2);
            $m = $months[$p] ?? null;
            // بعد الاعتماد، اللي بيترحّل هو رصيد لوحة ميتا (يعني مع التسوية)
            $carry = $m && $m->isApproved() && $m->meta_balance_usd !== null ? (float) $m->meta_balance_usd : $expected;
            $out[$p] = ['prev' => round($prev, 2), 'spend' => round((float) ($spend[$p] ?? 0), 2), 'paid' => round($paid[$p] ?? 0, 2),
                'expected' => $expected, 'carry' => round($carry, 2), 'month' => $m];
        }

        return $out;
    }

    /**
     * اعتماد الشهر: خدمة «إعلان ممول» على كشف كل زبون، ودين على حساب ميتا، وتسوية الفرق البسيط إذا في.
     */
    public function approve(string $period, float $metaBalance, bool $skipDiff): AdMonth
    {
        $rec = $this->reconciliation()[$period] ?? null;
        if (! $rec) {
            throw new FinanceException('ما في حملات أو فواتير بهالشهر.');
        }
        $prevPeriod = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->subMonthNoOverflow()->format('Y-m');
        if (isset($this->reconciliation()[$prevPeriod]) && ! $this->isApproved($prevPeriod)) {
            throw new FinanceException('اعتمد الشهر اللي قبله أول.');
        }
        $diff = round($metaBalance - $rec['expected'], 2);
        if (abs($diff) >= 0.005) {
            if (abs($diff) > $this->diffLimit() + 0.0001) {
                throw new FinanceException('الفرق مع لوحة ميتا '.number_format($diff, 2).' $، أكبر من الحد ('.number_format($this->diffLimit(), 2).' $). راجع الحملات والفواتير.');
            }
            if (! $skipDiff) {
                throw new FinanceException('في فرق بسيط '.number_format($diff, 2).' $. اضغط «تخطّي الفرق واعتماد» لتسجيل تسوية على حساب ميتا.');
            }
        }

        return DB::transaction(function () use ($period, $metaBalance, $rec, $diff) {
            $month = $this->month($period);
            if ($month->isApproved()) {
                throw new FinanceException('الشهر معتمد من قبل.');
            }
            $ils = Currency::byCode('ILS');
            $usd = Currency::byCode('USD');
            $type = $this->serviceType();
            $date = $period.'-01';
            $label = (int) substr($period, 5).'/'.substr($period, 0, 4);
            foreach (AdCampaign::query()->where('period', $period)->get() as $c) {
                $service = $this->work->create([
                    'client_id' => $c->client_id,
                    'service_type_id' => $type->id,
                    'title' => 'إعلان ممول · '.$c->platformLabel().' · شهر '.$label,
                    'amount' => $c->billed(),
                    'currency_id' => $ils->id,
                    'service_date' => $date,
                    'notes' => number_format((float) $c->spend_usd, 2).' $ × '.rtrim(rtrim(number_format((float) $c->sell_rate, 4), '0'), '.'),
                ]);
                $c->forceFill(['client_service_id' => $service->id])->save();
            }
            $meta = $this->metaVendor();
            $chargeId = null;
            if ($rec['spend'] > 0) {
                $chargeId = $this->charges->create([
                    'vendor_id' => $meta->id, 'title' => 'حملات شهر '.$label, 'amount' => $rec['spend'],
                    'currency_id' => $usd->id, 'charge_date' => $date,
                ])->id;
            }
            $ref = [null, null];
            if (abs($diff) >= 0.005) {
                if ($diff > 0) {
                    $ref = [$this->charges->create(['vendor_id' => $meta->id, 'title' => 'تسوية فرق ميتا شهر '.$label, 'amount' => $diff,
                        'currency_id' => $usd->id, 'charge_date' => $date])->id, 'charge'];
                } else {
                    $ref = [$this->takes->create(['party_type' => 'vendor', 'party_id' => $meta->id, 'title' => 'خصم', 'amount' => abs($diff),
                        'currency_id' => $usd->id, 'taken_on' => $date, 'notes' => 'تسوية فرق ميتا شهر '.$label])->id, 'goods'];
                }
            }
            $month->update(['status' => 'approved', 'meta_balance_usd' => $metaBalance, 'expected_usd' => $rec['expected'],
                'settlement_usd' => abs($diff) >= 0.005 ? $diff : null, 'vendor_charge_id' => $chargeId,
                'settlement_ref_id' => $ref[0], 'settlement_ref_type' => $ref[1], 'approved_at' => now()]);

            return $month;
        });
    }

    /**
     * تعديل أو حذف حملة بشهر معتمد: قيد عكسي (الخدمات والدين والتسوية بينلغوا، والسجلات بتضل محفوظة)، والشهر بيرجع مسودة.
     */
    public function reopen(string $period): bool
    {
        $month = AdMonth::query()->where('period', $period)->first();
        if (! $month || ! $month->isApproved()) {
            return false;
        }
        DB::transaction(function () use ($month, $period) {
            foreach (AdCampaign::query()->where('period', $period)->whereNotNull('client_service_id')->get() as $c) {
                if ($s = ClientService::query()->find($c->client_service_id)) {
                    $this->work->delete($s);
                }
                $c->forceFill(['client_service_id' => null])->save();
            }
            if ($month->vendor_charge_id && ($ch = VendorCharge::query()->find($month->vendor_charge_id))) {
                $this->charges->delete($ch);
            }
            if ($month->settlement_ref_type === 'charge' && ($ch = VendorCharge::query()->find($month->settlement_ref_id))) {
                $this->charges->delete($ch);
            } elseif ($month->settlement_ref_type === 'goods' && ($g = ClientGoodsTake::query()->find($month->settlement_ref_id))) {
                $this->takes->delete($g);
            }
            $month->update(['status' => 'draft', 'meta_balance_usd' => null, 'expected_usd' => null, 'settlement_usd' => null,
                'vendor_charge_id' => null, 'settlement_ref_id' => null, 'settlement_ref_type' => null, 'approved_at' => null]);
        });

        return true;
    }

    /** فاتورة ميتا: صرف عملة بنفس الدرج إذا الدرج بالشيكل، وبعدها دفعة بالدولار لحساب ميتا. */
    public function recordInvoice(array $data): MetaInvoice
    {
        return DB::transaction(function () use ($data) {
            $usd = Currency::byCode('USD');
            $ils = Currency::byCode('ILS');
            $amount = round((float) $data['amount_usd'], 2);
            $rate = (float) $data['usd_rate'];
            $transferId = null;
            if (($data['paid_in'] ?? 'ILS') === 'ILS') {
                $transferId = $this->transfers->transfer([
                    'fund_id' => $data['fund_id'], 'from_payment_method_id' => $data['payment_method_id'], 'to_payment_method_id' => $data['payment_method_id'],
                    'currency_id' => $ils->id, 'to_currency_id' => $usd->id, 'amount' => round($amount * $rate, 2), 'to_amount' => $amount,
                    'exchange_rate' => $rate, 'transfer_date' => $data['invoice_date'], 'notes' => 'صرف لفاتورة ميتا '.($data['meta_ref'] ?? ''),
                ])->id;
            }
            $meta = $this->metaVendor();
            $payment = $this->payments->record([
                'direction' => 'outgoing', 'party_type' => 'vendor', 'party_id' => $meta->id, 'name' => $meta->name,
                'fund_id' => $data['fund_id'], 'payment_method_id' => $data['payment_method_id'], 'currency_id' => $usd->id,
                'amount' => $amount, 'occurred_on' => $data['invoice_date'],
                'notes' => trim('فاتورة ميتا '.($data['meta_ref'] ?? '').' · سعر الدولار '.$rate),
            ]);

            return MetaInvoice::query()->create([
                'invoice_date' => $data['invoice_date'], 'amount_usd' => $amount, 'meta_ref' => $data['meta_ref'] ?? null,
                'fund_id' => $data['fund_id'], 'payment_method_id' => $data['payment_method_id'], 'usd_rate' => $rate,
                'cash_payment_id' => $payment->id, 'fund_transfer_id' => $transferId, 'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * الربح بالشيكل لكل شهر: الفواتير بتسدّ الأقدم أول، واللي ما انسد بينحسب بآخر سعر (تقديري).
     *
     * @return array<string, array{billed: float, cost: float, profit: float, confirmed: bool}>
     */
    public function profit(): array
    {
        $months = [];
        foreach (AdCampaign::query()->orderBy('period')->get() as $c) {
            $months[$c->period]['spend'] = ($months[$c->period]['spend'] ?? 0) + (float) $c->spend_usd;
            $months[$c->period]['billed'] = ($months[$c->period]['billed'] ?? 0) + $c->billed();
        }
        ksort($months);
        $queue = MetaInvoice::query()->orderBy('invoice_date')->orderBy('id')->get()
            ->map(fn ($i) => ['left' => (float) $i->amount_usd, 'rate' => (float) $i->usd_rate])->all();
        $lastRate = $queue ? end($queue)['rate'] : (\App\Support\Rates::today()['USD'] ?? 0);
        $qi = 0;
        $out = [];
        foreach ($months as $p => $m) {
            $need = $m['spend'];
            $cost = 0.0;
            while ($need > 0.005 && $qi < count($queue)) {
                $take = min($need, $queue[$qi]['left']);
                $cost += $take * $queue[$qi]['rate'];
                $need -= $take;
                $queue[$qi]['left'] -= $take;
                if ($queue[$qi]['left'] <= 0.005) {
                    $qi++;
                }
            }
            $confirmed = $need <= 0.005;
            $cost += max(0, $need) * $lastRate;
            $out[$p] = ['billed' => round($m['billed'], 2), 'cost' => round($cost, 2), 'profit' => round($m['billed'] - $cost, 2), 'confirmed' => $confirmed];
        }

        return $out;
    }

    /** @return Collection<int, AdCampaign> */
    public function campaigns(): Collection
    {
        return AdCampaign::query()->with('client')->orderByDesc('period')->orderBy('id')->get();
    }
}
