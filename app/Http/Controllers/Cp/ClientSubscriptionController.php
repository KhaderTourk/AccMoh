<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientSubscription;
use App\Models\Currency;
use App\Models\ServiceType;
use App\Services\Finance\ClientWorkService;
use App\Support\DateRange;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly subscriptions (#7 §3). Months are never billed automatically: each month shows up
 * on the confirmation page with a suggested amount and becomes a client service once confirmed.
 */
class ClientSubscriptionController extends Controller
{
    public function store(Request $request, ClientWorkService $work)
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'service_type_id' => ['nullable', 'required_without:title', 'exists:service_types,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'first_amount' => ['nullable', 'numeric', 'gte:0'],
            'starts_on' => DateRange::inputRules(),
            'notes' => ['nullable', 'string'],
        ]);
        $client = Client::query()->findOrFail($data['client_id']);
        $type = filled($data['service_type_id'] ?? null) ? ServiceType::query()->find($data['service_type_id']) : null;

        $sub = ClientSubscription::query()->create([
            'client_id' => $client->id,
            'service_type_id' => $type?->id,
            'title' => $type?->name ?: trim((string) $data['title']),
            'amount' => Money::of($data['amount']),
            // Subscriptions are billed in shekels, like the client's single balance.
            'currency_id' => Currency::byCode('ILS')->id,
            'starts_on' => DateRange::normalize($data['starts_on']),
            'notes' => $data['notes'] ?? null,
        ]);

        VendorSubscriptionController::keepFirstAmount($sub, $data['first_amount'] ?? null);

        // أول شهر ما بينسجّل لحاله: بيطلع بفلتر «الخدمات الشهرية» بمبلغ مقترح حسب الأيام (قرار 10/11/2026).
        $first = $sub->starts_on->format('Y-m');
        [$days, $inMonth] = $sub->daysIn($first);

        return redirect()->route('cp.clients.show', $client)
            ->with('success', 'انضاف الاشتراك. أول شهر'.($days < $inMonth ? " ({$days} يوم من {$inMonth})" : '').' بيطلع بفلتر «الخدمات الشهرية» عشان تضيفه.');
    }

    /** بيسجّل خدمة شهر من الاشتراك على كشف الزبون، إذا مش مسجّلة. */
    protected function recordMonth(ClientSubscription $sub, string $period, string $amount, ClientWorkService $work): bool
    {
        if (Money::isZero($amount) || $sub->months()->where('period', $period)->exists()) {
            return false;
        }
        $service = $work->create([
            'client_id' => $sub->client_id,
            'service_type_id' => $sub->service_type_id,
            'title' => $sub->monthTitle($period),
            'amount' => $amount,
            'currency_id' => $sub->currency_id,
            'service_date' => $sub->serviceDateFor($period),
        ]);
        $service->forceFill(['subscription_id' => $sub->id, 'period' => $period])->save();

        return true;
    }

    /**
     * «وقّف الاشتراك»: آخر يوم شغل، وبنفس الخطوة بيسجّل الشهر الأخير بالمبلغ اللي اختاره
     * (أو بيعدّل مبلغه إذا كان مسجّل قبل). المبلغ المقترح حسب الأيام، وقابل للتعديل.
     */
    public function stop(Request $request, ClientSubscription $subscription, ClientWorkService $work)
    {
        $data = $request->validate([
            'ends_on' => DateRange::inputRules(),
            'last_amount' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $endsOn = DateRange::normalize($data['ends_on']);
        if ($endsOn < $subscription->starts_on->toDateString()) {
            throw ValidationException::withMessages(['ends_on' => 'تاريخ الإيقاف قبل بداية الاشتراك.']);
        }

        $note = '';
        DB::transaction(function () use ($subscription, $endsOn, $work, $data, &$note) {
            $subscription->update([
                'ends_on' => $endsOn,
                'notes' => filled($data['notes'] ?? null) ? trim(($subscription->notes ? $subscription->notes."\n" : '').$data['notes']) : $subscription->notes,
            ]);
            $last = substr($endsOn, 0, 7);
            // الشهور المنضافة بعد شهر التوقيف بتنشال (صورة نسخة 5، 11/11/2026).
            foreach ($subscription->months()->where('period', '>', $last)->get() as $later) {
                $work->delete($later);
            }
            $amount = filled($data['last_amount'] ?? null) ? Money::of($data['last_amount']) : $subscription->suggestedAmount($last);
            $month = $subscription->months()->where('period', $last)->first();
            if ($month) {
                $work->update($month, [
                    'title' => $subscription->monthTitle($last),
                    'amount' => $amount,
                    'currency_id' => $month->currency_id,
                    'service_date' => $month->service_date->toDateString(),
                ]);
                $note = '، وتعدّل مبلغ شهر '.ClientSubscription::periodLabel($last).' لـ'.$amount.' شيكل';
            } elseif ($last <= now()->format('Y-m') && $this->recordMonth($subscription, $last, $amount, $work)) {
                $note = '، وانضاف شهر '.ClientSubscription::periodLabel($last).' بـ'.$amount.' شيكل';
            }
        });

        if ($request->input('return') === 'confirm') {
            return redirect()->route('cp.subscriptions.confirm')->with('success', 'انوقف الاشتراك «'.$subscription->title.'»'.$note.'.');
        }

        return redirect()->route('cp.clients.show', $subscription->client_id)->with('success', 'انوقف الاشتراك'.$note.'.');
    }

    public function destroy(ClientSubscription $subscription)
    {
        if ($subscription->months()->exists()) {
            return back()->with('error', 'الاشتراك إله أشهر مسجّلة، فما بينحذف. أوقفه بدل الحذف.');
        }
        $clientId = $subscription->client_id;
        $subscription->delete();

        return redirect()->route('cp.clients.show', $clientId)->with('success', 'انحذف الاشتراك.');
    }

    /** «خدمات شهر m/yyyy جاهزة للتأكيد»: every unrecorded month up to the chosen one. */
    public function confirm(Request $request)
    {
        $until = $this->month($request->query('month'));
        $rows = self::pending($until);

        return view('cp.finance.subscriptions.confirm', [
            'until' => $until,
            'untilLabel' => ClientSubscription::periodLabel($until),
            'rows' => $rows,
        ]);
    }

    public function confirmStore(Request $request, ClientWorkService $work)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.on' => ['nullable'],
            'items.*.amount' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $done = 0;
        DB::transaction(function () use ($data, $work, &$done) {
            foreach ($data['items'] as $key => $item) {
                if (empty($item['on']) || ! preg_match('/^(\d+)\|(\d{4}-\d{2})$/', (string) $key, $m)) {
                    continue;
                }
                $sub = ClientSubscription::query()->find((int) $m[1]);
                $period = $m[2];
                if (! $sub || ! $sub->isBillable($period) || $sub->months()->where('period', $period)->exists()) {
                    continue;
                }
                if ($this->recordMonth($sub, $period, Money::of($item['amount'] ?? $sub->suggestedAmount($period)), $work)) {
                    $done++;
                }
            }
        });

        $msg = $done ? "انسجّل {$done} شهر." : 'ما انسجّل إشي، يمكن الشهر انضاف قبل.';
        if ($request->input('return') === 'clients') {
            return redirect()->route('cp.clients.index')->with($done ? 'success' : 'error', $msg);
        }

        return redirect()->route('cp.subscriptions.confirm', ['month' => $request->input('month')])
            ->with('success', $msg);
    }

    /**
     * Invoices that can be issued now, up to the month $until: closed months by the days served,
     * and new full months from their first day. A running partial month waits for the next month.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function pending(string $until)
    {
        $today = CarbonImmutable::today();
        $current = $today->format('Y-m');

        return ClientSubscription::query()
            ->with(['client', 'months:id,subscription_id,period'])
            ->where('starts_on', '<=', CarbonImmutable::createFromFormat('Y-m-d', $until.'-01')->endOfMonth()->toDateString())
            ->orderBy('starts_on')
            ->get()
            ->filter(fn ($s) => $s->client)
            ->flatMap(fn (ClientSubscription $s) => collect($s->pendingMonths($until))
                ->filter(fn ($p) => $s->isBillable($p, $today))
                ->map(function ($p) use ($s, $current) {
                    [$days, $inMonth] = $s->daysIn($p);

                    return [
                        'key' => $s->id.'|'.$p,
                        'sub' => $s,
                        'period' => $p,
                        'periodLabel' => ClientSubscription::periodLabel($p),
                        'closed' => $p < $current,
                        'client' => $s->client,
                        'title' => $s->title,
                        'days' => $days,
                        'inMonth' => $inMonth,
                        'partial' => $days < $inMonth,
                        'amount' => $s->suggestedAmount($p),
                        'full' => Money::of($s->amount),
                    ];
                }))
            ->sortBy(fn ($r) => $r['period'].$r['client']->directoryLabel())
            ->values();
    }

    /**
     * اشتراكات كل زبون لفلتر «الخدمات الشهرية»: الشغّالة هالشهر أو اللي إلها شهر لسا ما انضاف.
     *
     * @return \Illuminate\Support\Collection<int, list<array<string, mixed>>>
     */
    public static function plansByClient()
    {
        $today = CarbonImmutable::today();
        $current = $today->format('Y-m');
        $action = route('cp.subscriptions.confirm.store');

        return ClientSubscription::query()
            ->with('months:id,subscription_id,period')
            ->where('starts_on', '<=', $today->endOfMonth()->toDateString())
            ->orderBy('starts_on')
            ->get()
            ->map(function (ClientSubscription $s) use ($today, $current, $action) {
                $pending = collect($s->pendingMonths($current))->filter(fn ($p) => $s->isBillable($p, $today))->values();
                if ($pending->isEmpty() && ! $s->coversMonth($current)) {
                    return null;
                }

                return [
                    'client_id' => $s->client_id,
                    'title' => $s->title,
                    'amount' => (float) $s->amount,
                    'from' => $s->starts_on->toDateString(),
                    'to' => $s->ends_on?->toDateString(),
                    'action' => $action,
                    'amountField' => null,
                    'pending' => $pending->map(function ($p) use ($s) {
                        [$days, $inMonth] = $s->daysIn($p);
                        $key = $s->id.'|'.$p;

                        return [
                            'period' => $p,
                            'label' => ClientSubscription::periodLabel($p),
                            'amount' => (float) $s->suggestedAmount($p),
                            'days' => $days,
                            'inMonth' => $inMonth,
                            'amountField' => "items[{$key}][amount]",
                            'fields' => ["items[{$key}][on]" => '1', 'month' => $p, 'return' => 'clients'],
                        ];
                    })->all(),
                ];
            })
            ->filter()
            ->groupBy('client_id');
    }

    public static function pendingCount(): int
    {
        return self::pending(now()->format('Y-m'))->count();
    }

    /** @return array{count: int, closed: int, fresh: int} */
    public static function pendingSummary(): array
    {
        $rows = self::pending(now()->format('Y-m'));

        return [
            'count' => $rows->count(),
            'closed' => $rows->where('closed', true)->count(),
            'fresh' => $rows->where('closed', false)->count(),
        ];
    }

    protected function month(?string $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? $value : now()->format('Y-m');
    }
}
