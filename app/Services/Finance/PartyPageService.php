<?php

namespace App\Services\Finance;

use App\Enums\BalanceMemoReason;
use App\Enums\GoodsTakeCategory;
use App\Enums\PaymentDirection;
use App\Enums\VendorType;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\ClientGoodsTake;
use App\Models\Currency;
use App\Models\PartyBalanceMemo;
use App\Models\Person;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Movements for the worker, supplier and person pages (#7 §7), in the same shape the client page uses.
 *
 * delta > 0 means: for a worker or supplier «إله» (we owe them); for a person «بدي منه» (they owe me).
 */
class PartyPageService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function movements(Vendor|Person $party): Collection
    {
        $isPerson = $party instanceof Person;
        $rows = collect();

        if (! $isPerson) {
            $isWorker = $party->type === VendorType::Worker;
            foreach ($party->charges()->with(['currency', 'fxCurrency'])->get() as $c) {
                $rows->push($this->row('charge', $c, $c->charge_date, $c->amount, $c->currency, $c->notes) + [
                    'group' => 'g1',
                    'kind' => $isWorker ? (str_starts_with((string) $c->title, 'راتب') ? 'راتب' : 'مستحق') : 'خدمة / مورد',
                    'title' => (string) $c->title,
                    'icon' => $isWorker ? 'badge' : 'local_shipping',
                    'edit' => route('cp.vendor-charges.edit', $c),
                    'delete' => route('cp.vendor-charges.destroy', $c),
                    'fx' => $this->fx($c),
                ]);
            }
        }

        $payments = $party->cashPayments()->active()->with(['currency', 'fxCurrency', 'paymentMethod'])->get();
        foreach ($payments as $p) {
            $out = $p->direction === PaymentDirection::Outgoing;
            if (! $isPerson && ! $out) {
                continue; // money a worker or supplier hands us does not touch their balance
            }
            $method = $p->paymentMethod?->name ?: 'دفعة';
            $advance = $p->kind === 'advance';
            $delta = $isPerson ? ($out ? $p->amount : Money::neg($p->amount)) : Money::neg($p->amount);
            $rows->push($this->row('payment', $p, $p->occurred_on, $p->amount, $p->currency, $p->notes, $delta) + [
                'group' => $advance ? 'other' : ($isPerson ? ($out ? 'g1' : 'g2') : 'g2'),
                'kind' => $advance ? 'سلفة' : $method,
                'title' => $advance ? 'سلفة · '.$method : ($isPerson ? ($out ? 'أعطيته · ' : 'أخدت منه · ') : 'دفعة · ').$method,
                'icon' => $advance ? 'savings' : ($isPerson && ! $out ? 'south_west' : 'payments'),
                'edit' => route('cp.payments.edit', $p),
                'delete' => route('cp.payments.destroy', $p),
                'fx' => $this->fx($p),
            ]);
        }

        foreach ($party->goodsTakes()->with(['currency', 'fxCurrency'])->get() as $g) {
            $discount = $g->title === GoodsTakeCategory::Discount->label();
            $rows->push($this->row('goods', $g, $g->taken_on, $g->amount, $g->currency, $g->notes, Money::neg($g->amount)) + [
                'group' => 'other',
                'kind' => $discount ? 'خصم' : 'بضاعة',
                'title' => $discount ? 'خصم' : 'بضاعة أخدها منا'.($g->title ? ' · '.$g->title : ''),
                'icon' => $discount ? 'percent' : 'inventory_2',
                'edit' => route('cp.client-goods-takes.edit', $g),
                'delete' => route('cp.client-goods-takes.destroy', $g),
                'fx' => $this->fx($g),
            ]);
        }

        foreach ($party->balanceMemos()->with('currency')->get() as $m) {
            $owed = $m->owedToUsDelta();
            $transfer = $m->reason === BalanceMemoReason::AccountTransfer;
            $rows->push($this->row('memo', $m, $m->memo_date, $m->amount, $m->currency, $m->notes, $isPerson ? $owed : Money::neg($owed)) + [
                'group' => 'other',
                'kind' => $transfer ? 'تحويل رصيد' : 'رصيد سابق',
                'title' => $m->statementTitle(),
                'icon' => $transfer ? 'swap_horiz' : 'history',
                'edit' => route('cp.balance-memos.edit', $m),
                'delete' => route('cp.balance-memos.destroy', $m),
                'fx' => null,
            ]);
        }

        return $rows->sortBy(fn ($r) => $r['date'].sprintf('%012d', $r['sort']))->values()
            ->map(function ($r) {
                unset($r['sort']);

                return $r;
            });
    }

    /**
     * Every other account with its balance as «owed to us» (positive = they owe us), for the transfer window.
     *
     * @return list<array<string, mixed>>
     */
    public function transferTargets(Person $except): array
    {
        $ils = Currency::byCode('ILS');
        $out = [];
        if (tenantBusinessEnabled()) {
            foreach (Client::query()->active()->get() as $c) {
                $out[] = ['key' => 'client:'.$c->id, 'name' => $c->directoryLabel(), 'type' => 'زبون', 'owed' => (float) $c->outstandingAmount($ils->id)];
            }
            foreach (Vendor::query()->active()->get() as $v) {
                $out[] = ['key' => 'vendor:'.$v->id, 'name' => $v->name, 'type' => $v->type->label(), 'owed' => -(float) $v->outstandingAmount($ils->id)];
            }
        }
        foreach (Person::query()->active()->whereKeyNot($except->id)->get() as $p) {
            $out[] = ['key' => 'person:'.$p->id, 'name' => $p->name, 'type' => 'شخص', 'owed' => (float) $p->netAmount($ils->id)];
        }

        return $out;
    }

    protected function row(string $type, $model, $date, $amount, Currency $currency, ?string $notes, ?string $delta = null): array
    {
        return [
            'id' => $type.'-'.$model->id,
            'type' => $type,
            'date' => $date->format('Y-m-d'),
            'sort' => $model->created_at?->getTimestamp() ?? $model->id,
            'currency' => $currency->code,
            'symbol' => $currency->symbol,
            'delta' => (float) ($delta ?? $amount),
            'amount' => (float) $amount,
            'notes' => (string) ($notes ?? ''),
        ];
    }

    protected function fx($model): ?string
    {
        if (! $model->fx_currency_id || ! $model->exchange_rate || ! $model->fxCurrency) {
            return null;
        }

        return rtrim(rtrim((string) $model->source_amount, '0'), '.').' '.$model->fxCurrency->code
            .' × '.rtrim(rtrim((string) $model->exchange_rate, '0'), '.');
    }
}
