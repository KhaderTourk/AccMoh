<?php

namespace App\Services\Finance;

use App\Models\Client;
use App\Models\Currency;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Support\Collection;

class ClientStatementService
{
    /**
     * @param  array<int|string, mixed>  $openingOverrides
     * @return array<string, mixed>
     */
    public function build(Client $client, ?string $from, ?string $to, array $openingOverrides = []): array
    {
        $from = DateRange::normalize($from);
        $to = DateRange::normalize($to);
        if ($from && $to && strcmp($from, $to) > 0) {
            [$from, $to] = [$to, $from];
        }

        $client->load([
            'services' => fn ($q) => $q->with(['currency', 'fxCurrency', 'serviceType'])
                ->tap(fn ($qq) => DateRange::constrain($qq, 'service_date', $from, $to))
                ->orderBy('service_date')
                ->orderBy('id'),
            'payments' => fn ($q) => $q->active()->with(['currency', 'fxCurrency', 'paymentMethod'])
                ->tap(fn ($qq) => DateRange::constrain($qq, 'occurred_on', $from, $to))
                ->orderBy('occurred_on')
                ->orderBy('id'),
            'goodsTakes' => fn ($q) => $q->with(['currency', 'fxCurrency'])
                ->tap(fn ($qq) => DateRange::constrain($qq, 'taken_on', $from, $to))
                ->orderBy('taken_on')
                ->orderBy('id'),
            'balanceMemos' => fn ($q) => $q->with('currency')
                ->tap(fn ($qq) => DateRange::constrain($qq, 'memo_date', $from, $to))
                ->orderBy('memo_date')
                ->orderBy('id'),
        ]);

        $currencies = Currency::query()->active()->get();
        $hasOpening = filled($from);
        $summaries = [];

        foreach ($currencies as $currency) {
            $computedOpening = $hasOpening ? $client->openingBalance($currency->id, $from) : '0.00';
            $periodBilled = $client->billedAmount($currency->id, $from, $to);
            $periodPaid = $client->paidAmount($currency->id, $from, $to);
            $periodGoods = $client->goodsTakenAmount($currency->id, $from, $to);
            $periodMemos = $client->memoDelta($currency->id, $from, $to);

            $opening = $computedOpening;
            $openingOverridden = false;
            $rawOverride = $openingOverrides[$currency->id] ?? $openingOverrides[(string) $currency->id] ?? null;
            if ($hasOpening && $rawOverride !== null && $rawOverride !== '') {
                $opening = Money::of($rawOverride);
                $openingOverridden = Money::cmp($opening, $computedOpening) !== 0;
            }

            $closing = Money::add(
                Money::sub(Money::sub(Money::add($opening, $periodBilled), $periodPaid), $periodGoods),
                $periodMemos
            );
            $lifetimeDue = $client->outstandingAmount($currency->id);

            if (
                Money::isZero($computedOpening)
                && Money::isZero($periodBilled)
                && Money::isZero($periodPaid)
                && Money::isZero($periodGoods)
                && Money::isZero($periodMemos)
                && Money::isZero($lifetimeDue)
                && ! $openingOverridden
            ) {
                continue;
            }

            $summaries[] = [
                'currency' => $currency,
                'computed_opening' => $computedOpening,
                'opening' => $opening,
                'opening_overridden' => $openingOverridden,
                'billed' => $periodBilled,
                'paid' => $periodPaid,
                'goods' => $periodGoods,
                'memos' => $periodMemos,
                'closing' => $closing,
                'lifetime_due' => $lifetimeDue,
            ];
        }

        $payments = $client->payments;
        $services = $client->services;
        $goodsTakes = $client->goodsTakes;
        $balanceMemos = $client->balanceMemos;

        $timeline = collect();
        foreach ($services as $service) {
            $timeline->push([
                'id' => $service->id,
                'sort_at' => $service->created_at?->getTimestamp() ?? $service->id,
                'date' => $service->service_date,
                'type' => 'service',
                'title' => 'خدمة: '.$service->title,
                'amount' => $service->amount,
                'delta' => $service->status?->countsTowardReceivable() ? $service->amount : '0.00',
                'currency' => $service->currency,
                'notes' => $service->notes,
                'fx' => $this->fxNote($service),
            ]);
        }
        foreach ($payments->where('is_reversed', false) as $payment) {
            $timeline->push([
                'id' => $payment->id,
                'sort_at' => $payment->created_at?->getTimestamp() ?? $payment->id,
                'date' => $payment->occurred_on,
                'type' => 'payment',
                'title' => 'دفعة عبر '.$payment->paymentMethod->name,
                'amount' => $payment->amount,
                'delta' => Money::neg($payment->amount),
                'currency' => $payment->currency,
                'notes' => $payment->notes,
                'fx' => $this->fxNote($payment),
            ]);
        }
        foreach ($balanceMemos as $memo) {
            $timeline->push([
                'id' => $memo->id,
                'sort_at' => $memo->created_at?->getTimestamp() ?? $memo->id,
                'date' => $memo->memo_date,
                'type' => 'memo',
                'title' => $memo->statementTitle(),
                'amount' => $memo->amount,
                'delta' => $memo->owedToUsDelta(),
                'currency' => $memo->currency,
                'notes' => $memo->notes,
            ]);
        }
        foreach ($goodsTakes as $take) {
            $timeline->push([
                'id' => $take->id,
                'sort_at' => $take->created_at?->getTimestamp() ?? $take->id,
                'date' => $take->taken_on,
                'type' => 'goods',
                'title' => 'بضاعة مأخوذة: '.$take->title,
                'amount' => $take->amount,
                'delta' => Money::neg($take->amount),
                'currency' => $take->currency,
                'notes' => $take->notes,
                'fx' => $this->fxNote($take),
            ]);
        }

        $timeline = $this->withBalanceAfterEachMovement($timeline, $summaries, $client, $from, $hasOpening || $to);

        return [
            'client' => $client,
            'currencies' => $currencies,
            'from' => $from,
            'to' => $to,
            'periodLabel' => DateRange::label($from, $to),
            'hasOpening' => $hasOpening,
            'summaries' => $summaries,
            'serviceGroups' => $this->groupServices($services),
            'paymentGroups' => $this->groupPayments($payments),
            'goodsTakes' => $goodsTakes->sortByDesc(fn ($t) => $t->taken_on->format('Y-m-d').sprintf('%010d', $t->id))->values(),
            'balanceMemos' => $balanceMemos->sortByDesc(fn ($m) => $m->memo_date->format('Y-m-d').sprintf('%010d', $m->id))->values(),
            'goodsTotals' => $this->totalsByCurrency($goodsTakes),
            'timeline' => $timeline,
            'movementCount' => $services->count() + $payments->where('is_reversed', false)->count() + $goodsTakes->count() + $balanceMemos->count(),
            'exportedAt' => format_date(now(), true),
            'title' => $client->personName(),
            'subtitle' => trim(implode(' · ', array_filter([$client->organization(), $client->phone]))),
        ];
    }

    /** «100 USD × 3.65» for a movement entered in a foreign currency, else null. */
    protected function fxNote($model): ?string
    {
        if (! $model->fx_currency_id || ! $model->exchange_rate || ! $model->fxCurrency) {
            return null;
        }

        return rtrim(rtrim((string) $model->source_amount, '0'), '.').' '.$model->fxCurrency->code
            .' × '.rtrim(rtrim((string) $model->exchange_rate, '0'), '.');
    }

    /**
     * @param  list<array<string, mixed>>  $summaries
     */
    protected function withBalanceAfterEachMovement(Collection $timeline, array $summaries, Client $client, ?string $from, bool $oldestFirst): Collection
    {
        $running = [];
        foreach ($summaries as $summary) {
            $running[(int) $summary['currency']->id] = $summary['opening'];
        }

        $chronological = $timeline
            ->sortBy(fn ($item) => $item['date']->format('Y-m-d').sprintf('%010d', $item['sort_at']).sprintf('%010d', $item['id']))
            ->values();

        $balanced = $chronological->map(function (array $item) use (&$running, $client, $from) {
            $currencyId = (int) $item['currency']->id;
            if (! array_key_exists($currencyId, $running)) {
                $running[$currencyId] = $from ? $client->openingBalance($currencyId, $from) : '0.00';
            }

            $running[$currencyId] = Money::add($running[$currencyId], $item['delta']);
            $item['balance'] = $running[$currencyId];

            return $item;
        });

        $displayKey = fn ($item) => $item['date']->format('Y-m-d').sprintf('%010d', $item['sort_at']).sprintf('%010d', $item['id']);

        return $oldestFirst
            ? $balanced->sortBy($displayKey)->values()
            : $balanced->sortByDesc($displayKey)->values();
    }

    protected function groupServices(Collection $services): Collection
    {
        return $services
            ->groupBy(fn ($s) => $s->service_type_id ?: 0)
            ->map(function (Collection $rows) {
                $type = $rows->first()->serviceType;
                $rows = $rows->sortBy(fn ($s) => $s->service_date->format('Y-m-d').sprintf('%010d', $s->id))->values();

                return [
                    'name' => $type?->name ?: 'بدون نوع',
                    'uncategorized' => $type === null,
                    'services' => $rows,
                    'totals' => $this->totalsByCurrency($rows),
                ];
            })
            ->sortBy(fn ($group) => ($group['uncategorized'] ? '1-' : '0-').$group['name'])
            ->values();
    }

    protected function groupPayments(Collection $payments): Collection
    {
        return $payments
            ->groupBy('payment_method_id')
            ->map(function (Collection $rows) {
                $method = $rows->first()->paymentMethod;
                $rows = $rows->sortByDesc(fn ($p) => $p->occurred_on->format('Y-m-d').sprintf('%010d', $p->id))->values();

                return [
                    'name' => $method?->name ?: '—',
                    'sort' => $method?->sort_order ?? 999,
                    'payments' => $rows,
                    'totals' => $this->totalsByCurrency($rows->where('is_reversed', false)),
                ];
            })
            ->sortBy('sort')
            ->values();
    }

    protected function totalsByCurrency(Collection $rows): Collection
    {
        return $rows
            ->groupBy('currency_id')
            ->map(function (Collection $byCurrency) {
                $currency = $byCurrency->first()->currency;
                $total = $byCurrency->reduce(fn ($sum, $row) => Money::add($sum, $row->amount), '0');

                return [
                    'currency' => $currency,
                    'total' => $total,
                    'formatted' => $currency->format($total),
                ];
            })
            ->values();
    }
}
