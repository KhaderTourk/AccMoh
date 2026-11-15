<?php

namespace App\Http\Controllers\Cp;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Vendor;
use App\Models\VendorChargeType;
use App\Models\VendorSubscription;
use App\Services\Finance\VendorChargeService;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\MonthlyPlan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الراتب الشهري (موظف) والاشتراك الشهري (مورد) على نوع مستحق (قرار 11/11/2026، صورة نسخة 5).
 * «حفظ» ما بيضيف مبالغ: كل شهر بينضاف لحاله بزر «أضف» من فلتر «الرواتب/الخدمات الشهرية».
 */
class VendorSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'charge_type_id' => ['nullable', 'required_without:title', 'exists:vendor_charge_types,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'first_amount' => ['nullable', 'numeric', 'gte:0'],
            'starts_on' => DateRange::inputRules(),
            'notes' => ['nullable', 'string'],
        ]);
        $vendor = Vendor::query()->findOrFail($data['vendor_id']);
        $type = filled($data['charge_type_id'] ?? null) ? VendorChargeType::query()->find($data['charge_type_id']) : null;

        $sub = VendorSubscription::query()->create([
            'vendor_id' => $vendor->id,
            'charge_type_id' => $type?->id,
            'title' => $type?->name ?: trim((string) $data['title']),
            'amount' => Money::of($data['amount']),
            'currency_id' => Currency::byCode('ILS')->id,
            'starts_on' => DateRange::normalize($data['starts_on']),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->keepFirstAmount($sub, $data['first_amount'] ?? null);

        $list = $vendor->type === VendorType::Worker ? 'الرواتب الشهرية' : 'الخدمات الشهرية';

        return redirect()->route('cp.'.$vendor->type->routePrefix().'.show', $vendor)
            ->with('success', 'انحفظ. أول شهر بيطلع بفلتر «'.$list.'» عشان تضيفه.');
    }

    /** «مبلغ أول شهر» بينحفظ بس إذا اختلف عن المقترح. */
    public static function keepFirstAmount($sub, mixed $first): void
    {
        if (! filled($first)) {
            return;
        }
        $period = $sub->starts_on->format('Y-m');
        $suggested = MonthlyPlan::suggested($sub->amount, $sub->starts_on, $sub->ends_on, $period);
        if (Money::cmp(Money::of($first), $suggested) !== 0) {
            $sub->forceFill(['first_amount' => Money::of($first)])->save();
        }
    }

    /** «أضف»: بيسجّل مستحق شهر واحد، وما بينضاف نفس الشهر مرتين. */
    public function addMonth(Request $request, VendorSubscription $subscription, VendorChargeService $charges)
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);
        $vendor = $subscription->vendor;
        $back = redirect()->route('cp.'.$vendor->type->routePrefix().'.index');
        $added = DB::transaction(function () use ($subscription, $data, $charges) {
            VendorSubscription::query()->whereKey($subscription->id)->lockForUpdate()->first();

            return $this->recordMonth($subscription, $data['period'], Money::of($data['amount']), $charges);
        });

        return $added
            ? $back->with('success', 'انضاف '.$subscription->title.' شهر '.MonthlyPlan::label($data['period']).' لـ'.$vendor->name.'.')
            : $back->with('error', 'الشهر '.MonthlyPlan::label($data['period']).' انضاف قبل، أو مش ضمن '.$subscription->noun().'.');
    }

    /**
     * نافذة «مستحق ← راتب» بصفحة الموظف (شاشة الموظفين، المرحلة 3، 15/11/2026)، بنفس آلية اشتراك الزبون (قرار 18):
     * «عن شهر» لحاله، والحركة بآخر يوم بالشهر (حتى أول شهر)، إلا آخر شهر فبيوم ما وقف.
     * أول شهر: «بلّش شغل يوم» بيصير بداية الراتب. آخر شهر: «آخر يوم شغل» بيوقّفه وبيشيل الشهور المنضافة بعده.
     * «أضيفه مرة ثانية» لشهر انضاف قبل بيتسجّل مستحق عادي مش مربوط بالشهر.
     */
    public function addFromPage(Request $request, VendorSubscription $subscription, VendorChargeService $charges)
    {
        $currency = Currency::query()->find($request->input('currency_id'));
        $isFx = $currency && $currency->code !== 'ILS';
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'start_day' => ['nullable', 'integer', 'between:1,31'],
            'stop' => ['nullable', 'boolean'],
            'end_day' => ['nullable', 'required_if:stop,1', 'integer', 'between:1,31'],
            'again' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
        $period = $data['period'];
        $first = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $last = $first->endOfMonth()->startOfDay();
        $inMonth = (int) $last->format('j');
        $startDay = filled($data['start_day'] ?? null) ? min($inMonth, (int) $data['start_day']) : null;
        $stop = $request->boolean('stop');
        $endDay = $stop ? min($inMonth, max($startDay ?? 1, (int) $data['end_day'])) : null;

        $linked = $subscription->months()->where('period', $period)->exists();
        if ($linked && ! $request->boolean('again')) {
            throw ValidationException::withMessages(['period' => 'شهر '.MonthlyPlan::label($period).' انضاف قبل لهالموظف.']);
        }

        $money = \App\Support\PaymentFx::hydrate([
            'currency_id' => $data['currency_id'],
            'amount' => $data['amount'] ?? null,
            'source_amount' => $isFx ? ($data['source_amount'] ?? $data['amount'] ?? null) : null,
            'exchange_rate' => $data['exchange_rate'] ?? null,
        ]);

        DB::transaction(function () use ($subscription, $charges, $data, $money, $period, $first, $last, $startDay, $stop, $endDay, $linked) {
            if ($startDay !== null || $first->toDateString() < $subscription->starts_on->toDateString()) {
                $subscription->update(['starts_on' => $first->day($startDay ?? 1)->toDateString()]);
            }
            $date = $last;
            if ($stop) {
                $date = $first->day($endDay);
                $subscription->update(['ends_on' => $date->toDateString()]);
                foreach ($subscription->months()->where('period', '>', $period)->get() as $later) {
                    $charges->delete($later);
                }
            }
            $charges->create([
                'vendor_id' => $subscription->vendor_id,
                'charge_type_id' => $subscription->charge_type_id,
                'subscription_id' => $linked ? null : $subscription->id,
                'period' => $linked ? null : $period,
                'title' => $subscription->title,
                'amount' => $money['amount'] ?? ($data['amount'] ?? null),
                'source_amount' => $money['source_amount'] ?? null,
                'exchange_rate' => $money['exchange_rate'] ?? null,
                'fx_currency_id' => $money['fx_currency_id'] ?? null,
                'currency_id' => $money['currency_id'],
                'charge_date' => $date->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $vendor = $subscription->vendor;
        $label = MonthlyPlan::label($period);

        return redirect()->route('cp.'.$vendor->type->routePrefix().'.show', $vendor)
            ->with('success', $stop ? "انضاف آخر شهر ({$label}) ووقف {$subscription->title}." : "انضاف {$subscription->title} شهر {$label}.");
    }

    protected function recordMonth(VendorSubscription $sub, string $period, string $amount, VendorChargeService $charges): bool
    {
        if (Money::isZero($amount) || ! in_array($period, $sub->pendingMonths(now()->format('Y-m')), true)) {
            return false;
        }
        $charges->create([
            'vendor_id' => $sub->vendor_id,
            'charge_type_id' => $sub->charge_type_id,
            'subscription_id' => $sub->id,
            'period' => $period,
            'title' => $sub->monthTitle($period),
            'amount' => $amount,
            'currency_id' => $sub->currency_id,
            'charge_date' => $period.'-01',
        ]);

        return true;
    }

    /**
     * «وقّف»: آخر شهر وآخر يوم شغل. إذا الشهر منضاف، بيتعدّل نفس المستحق؛ وإلا بينضاف.
     * الشهور المنضافة بعد شهر التوقيف بتنشال (صورة نسخة 5).
     */
    public function stop(Request $request, VendorSubscription $subscription, VendorChargeService $charges)
    {
        $data = $request->validate([
            'ends_on' => DateRange::inputRules(),
            'last_amount' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $endsOn = DateRange::normalize($data['ends_on']);
        if ($endsOn < $subscription->starts_on->toDateString()) {
            throw ValidationException::withMessages(['ends_on' => 'آخر يوم شغل قبل البداية.']);
        }

        $note = '';
        DB::transaction(function () use ($subscription, $endsOn, $data, $charges, &$note) {
            $subscription->update([
                'ends_on' => $endsOn,
                'notes' => filled($data['notes'] ?? null) ? trim(($subscription->notes ? $subscription->notes."\n" : '').$data['notes']) : $subscription->notes,
            ]);
            $last = substr($endsOn, 0, 7);
            foreach ($subscription->months()->where('period', '>', $last)->get() as $later) {
                $charges->delete($later);
            }
            $amount = filled($data['last_amount'] ?? null) ? Money::of($data['last_amount']) : $subscription->suggestedAmount($last);
            $month = $subscription->months()->where('period', $last)->first();
            if ($month) {
                $charges->update($month, [
                    'title' => $subscription->monthTitle($last),
                    'amount' => $amount,
                    'currency_id' => $month->currency_id,
                    'charge_date' => $month->charge_date->toDateString(),
                    'notes' => $month->notes,
                ]);
                $note = '، وتعدّل شهر '.MonthlyPlan::label($last).' لـ'.$amount.' شيكل';
            } elseif ($last <= now()->format('Y-m') && $this->recordMonth($subscription, $last, $amount, $charges)) {
                $note = '، وانضاف شهر '.MonthlyPlan::label($last).' بـ'.$amount.' شيكل';
            }
        });

        $vendor = $subscription->vendor;

        return redirect()->route('cp.'.$vendor->type->routePrefix().'.show', $vendor)
            ->with('success', 'انوقف '.$subscription->noun().' «'.$subscription->title.'»'.$note.'.');
    }

    public function destroy(VendorSubscription $subscription)
    {
        if ($subscription->months()->exists()) {
            return back()->with('error', 'إله شهور منضافة، فما بينحذف. وقّفه بدل الحذف.');
        }
        $vendor = $subscription->vendor;
        $subscription->delete();

        return redirect()->route('cp.'.$vendor->type->routePrefix().'.show', $vendor)->with('success', 'انحذف.');
    }

    /**
     * لفلتر «الرواتب الشهرية» و«الخدمات الشهرية»: الاشتراكات الشغّالة هالشهر أو اللي إلها شهر لسا ما انضاف.
     *
     * @return list<array<string, mixed>>
     */
    public static function plans(Vendor $vendor): array
    {
        $today = CarbonImmutable::today();
        $current = $today->format('Y-m');
        $subs = $vendor->relationLoaded('subscriptions') ? $vendor->subscriptions : $vendor->subscriptions()->with('months:id,subscription_id,period')->get();

        return $subs->sortBy('starts_on')->map(function (VendorSubscription $s) use ($today, $current) {
            $pending = collect($s->pendingMonths($current))->filter(fn ($p) => $s->isBillable($p, $today))->values();
            if ($pending->isEmpty() && ! $s->coversMonth($current)) {
                return null;
            }

            return [
                'title' => $s->title,
                'amount' => (float) $s->amount,
                'from' => $s->starts_on->toDateString(),
                'to' => $s->ends_on?->toDateString(),
                'action' => route('cp.vendor-subscriptions.add', $s),
                'pending' => $pending->map(function ($p) use ($s) {
                    [$days, $inMonth] = $s->daysIn($p);

                    return [
                        'period' => $p,
                        'label' => MonthlyPlan::label($p),
                        'amount' => (float) $s->suggestedAmount($p),
                        'days' => $days,
                        'inMonth' => $inMonth,
                        'amountField' => 'amount',
                        'fields' => ['period' => $p],
                    ];
                })->all(),
            ];
        })->filter()->values()->all();
    }

    /** اشتراكات الحساب لصفحته (اللوحة فوق الحركات ونافذة «وقّف»). */
    public static function forPage(Vendor $vendor): array
    {
        $current = now()->format('Y-m');

        return $vendor->subscriptions()->with('months:id,subscription_id,period,amount,charge_date')->orderBy('starts_on')->get()
            ->map(fn (VendorSubscription $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'amount' => (float) $s->amount,
                'from' => $s->starts_on->toDateString(),
                'to' => $s->ends_on?->toDateString(),
                'first' => $s->first_amount !== null ? (float) $s->first_amount : null,
                'months' => $s->months->count(),
                'added' => $s->months->filter(fn ($m) => $m->period)->mapWithKeys(fn ($m) => [$m->period => (float) $m->amount])->all(),
                'type' => $s->charge_type_id,
                'dates' => $s->months->filter(fn ($m) => $m->period)->mapWithKeys(fn ($m) => [$m->period => $m->charge_date?->toDateString()])->all(),
                'url' => route('cp.vendor-subscriptions.month', $s),
                'due' => collect($s->pendingMonths($current))->filter(fn ($p) => $s->isBillable($p))->count(),
                'stop' => route('cp.vendor-subscriptions.stop', $s),
                'delete' => route('cp.vendor-subscriptions.destroy', $s),
            ])->values()->all();
    }
}
