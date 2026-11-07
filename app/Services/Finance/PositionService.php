<?php

namespace App\Services\Finance;

use App\Enums\VendorType;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Person;
use App\Models\Vendor;
use App\Support\Rates;

/**
 * الرئيسية (تسليم 1/11 §9): «إلي» و«علي» بالشيكل بسعر اليوم، من أرصدة الأطراف الموجودة.
 */
class PositionService
{
    /**
     * @return array{owed: array<string, float>, owe: array<string, float>, topClients: list<array>, topVendors: list<array>}
     */
    public function summary(): array
    {
        $currencies = Currency::query()->active()->get();
        $ils = function (callable $amount) use ($currencies): float {
            $sum = 0.0;
            foreach ($currencies as $c) {
                $sum += Rates::toIls((float) $amount($c->id), $c->code);
            }

            return round($sum, 2);
        };

        $owed = ['clients' => 0.0, 'persons' => 0.0, 'vendors' => 0.0];
        $owe = ['workers' => 0.0, 'suppliers' => 0.0, 'persons' => 0.0, 'clients' => 0.0];
        $clients = [];
        $vendors = [];

        if (tenantBusinessEnabled()) {
            foreach (Client::query()->active()->get() as $c) {
                $v = $ils(fn ($id) => $c->outstandingAmount($id));
                $v > 0 ? $owed['clients'] += $v : $owe['clients'] -= $v;
                if ($v > 0.005) {
                    $clients[] = ['name' => $c->directoryLabel(), 'amount' => $v, 'url' => route('cp.clients.show', $c)];
                }
            }
            foreach (Vendor::query()->active()->get() as $p) {
                // موجب = إلهم عندنا
                $v = $ils(fn ($id) => $p->outstandingAmount($id));
                $worker = $p->type === VendorType::Worker;
                if ($v > 0) {
                    $owe[$worker ? 'workers' : 'suppliers'] += $v;
                } else {
                    $owed['vendors'] -= $v;
                }
                if ($v > 0.005) {
                    $vendors[] = ['name' => $p->name, 'amount' => $v, 'type' => $worker ? 'موظف' : 'مورد',
                        'url' => route($worker ? 'cp.workers.show' : 'cp.suppliers.show', $p)];
                }
            }
        }
        foreach (Person::query()->active()->get() as $p) {
            $v = $ils(fn ($id) => $p->netAmount($id));
            $v > 0 ? $owed['persons'] += $v : $owe['persons'] -= $v;
        }

        usort($clients, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        usort($vendors, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'owed' => array_map(fn ($x) => round($x, 2), $owed),
            'owe' => array_map(fn ($x) => round($x, 2), $owe),
            'topClients' => array_slice($clients, 0, 5),
            'topVendors' => array_slice($vendors, 0, 3),
        ];
    }
}
