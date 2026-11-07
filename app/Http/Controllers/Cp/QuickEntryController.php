<?php

namespace App\Http\Controllers\Cp;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Person;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;

/** «＋ تسجيل سريع» (تسليم 1/11 §11): قائمة الحسابات لنافذة الاختيار. */
class QuickEntryController extends Controller
{
    public function accounts(): JsonResponse
    {
        $out = [];
        if (tenantBusinessEnabled()) {
            foreach (Client::query()->active()->get() as $c) {
                $out[] = ['key' => 'client:'.$c->id, 'name' => $c->directoryLabel(), 'type' => 'client', 'url' => route('cp.clients.show', $c)];
            }
            foreach (Vendor::query()->active()->orderBy('name')->get() as $v) {
                $worker = $v->type === VendorType::Worker;
                $out[] = ['key' => 'vendor:'.$v->id, 'name' => $v->name, 'type' => $worker ? 'worker' : 'supplier',
                    'url' => route($worker ? 'cp.workers.show' : 'cp.suppliers.show', $v)];
            }
        }
        foreach (Person::query()->active()->orderBy('name')->get() as $p) {
            $out[] = ['key' => 'person:'.$p->id, 'name' => $p->name, 'type' => 'person', 'url' => route('cp.persons.show', $p)];
        }

        return response()->json(['accounts' => $out, 'transfer' => route('cp.transfers.create')]);
    }
}
