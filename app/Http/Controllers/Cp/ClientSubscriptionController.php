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
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'service_type_id' => ['nullable', 'required_without:title', 'exists:service_types,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'starts_on' => DateRange::inputRules(),
            'notes' => ['nullable', 'string'],
        ]);
        $client = Client::query()->findOrFail($data['client_id']);
        $type = filled($data['service_type_id'] ?? null) ? ServiceType::query()->find($data['service_type_id']) : null;

        ClientSubscription::query()->create([
            'client_id' => $client->id,
            'service_type_id' => $type?->id,
            'title' => $type?->name ?: trim((string) $data['title']),
            'amount' => Money::of($data['amount']),
            // Subscriptions are billed in shekels, like the client's single balance.
            'currency_id' => Currency::byCode('ILS')->id,
            'starts_on' => DateRange::normalize($data['starts_on']),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('cp.clients.show', $client)
            ->with('success', 'انضاف الاشتراك. الشهر الأول بيتحسب من يوم البداية، وأول الشهر الجاي بتطلع فاتورته بعدد الأيام مع فاتورة الشهر الجديد.');
    }

    /** Stop a subscription; a month already recorded for the last month gets its amount by days. */
    public function stop(Request $request, ClientSubscription $subscription, ClientWorkService $work)
    {
        $data = $request->validate(['ends_on' => DateRange::inputRules()]);
        $endsOn = DateRange::normalize($data['ends_on']);
        if ($endsOn < $subscription->starts_on->toDateString()) {
            throw ValidationException::withMessages(['ends_on' => 'تاريخ الإيقاف قبل بداية الاشتراك.']);
        }

        DB::transaction(function () use ($subscription, $endsOn, $work) {
            $subscription->update(['ends_on' => $endsOn]);
            $last = substr($endsOn, 0, 7);
            $month = $subscription->months()->where('period', $last)->first();
            if ($month) {
                $amount = $subscription->suggestedAmount($last);
                $work->update($month, [
                    'title' => $subscription->monthTitle($last),
                    'amount' => $amount,
                    'currency_id' => $month->currency_id,
                    'service_date' => $month->service_date->toDateString(),
                ]);
            }
        });

        if ($request->input('return') === 'confirm') {
            return redirect()->route('cp.subscriptions.confirm')->with('success', 'انوقف الاشتراك «'.$subscription->title.'».');
        }

        return redirect()->route('cp.clients.show', $subscription->client_id)->with('success', 'انوقف الاشتراك.');
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
                $amount = Money::of($item['amount'] ?? $sub->suggestedAmount($period));
                if (Money::isZero($amount)) {
                    continue;
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
                $done++;
            }
        });

        return redirect()->route('cp.subscriptions.confirm', ['month' => $request->input('month')])
            ->with('success', $done ? "انسجّل {$done} شهر." : 'ما انسجّل إشي.');
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
