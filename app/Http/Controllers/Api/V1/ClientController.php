<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\ClientController as CpClientController;
use App\Models\Client;
use App\Models\Currency;
use App\Services\Finance\ClientStatementService;
use App\Support\DateRange;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientController extends CpClientController
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = DateRange::fromRequest($request);
        $currencies = Currency::query()->active()->get();

        $clients = TableSort::paginate(
            Client::query()
                ->active()
                ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('company_name', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                    if (Schema::hasColumn('clients', 'contact_name')) {
                        $qq->orWhere('contact_name', 'like', "%{$term}%");
                    }
                }))
                ->when($from || $to, function ($q) use ($from, $to) {
                    $q->where(function ($qq) use ($from, $to) {
                        $qq->whereHas('services', fn ($s) => DateRange::constrain($s, 'service_date', $from, $to))
                            ->orWhereHas('cashPayments', fn ($p) => DateRange::constrain($p->active(), 'occurred_on', $from, $to))
                            ->orWhereHas('goodsTakes', fn ($g) => DateRange::constrain($g, 'taken_on', $from, $to))
                            ->orWhereHas('balanceMemos', fn ($m) => DateRange::constrain($m, 'memo_date', $from, $to));
                    });
                }),
            [
                'company' => ['column' => 'company_name'],
                'name' => ['column' => 'name'],
                'phone' => ['column' => 'phone'],
                'due' => [
                    'dir' => 'desc',
                    'value' => function (Client $client) use ($currencies) {
                        $sum = 0.0;
                        foreach ($currencies as $currency) {
                            $sum += (float) $client->outstandingAmount($currency->id);
                        }

                        return $sum;
                    },
                ],
            ],
            'name'
        );

        $clients->getCollection()->transform(fn (Client $client) => $client->toArray() + [
            'outstanding' => $currencies->mapWithKeys(fn ($c) => [$c->code => $client->outstandingAmount($c->id)]),
        ]);

        return response()->json($clients);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->has('is_active')) {
            $request->merge(['is_active' => true]);
        }
        $client = Client::query()->create($this->validated($request));

        return $this->created($client, 'تم إضافة الزبون.');
    }

    public function show(Request $request, Client $client, ClientStatementService $statements): JsonResponse
    {
        $request->validate([
            'opening' => ['sometimes', 'array'],
            'opening.*' => ['nullable', 'numeric'],
        ]);

        $payload = $this->showPayload($request, $client, $statements);
        unset($payload['exportedAt'], $payload['title'], $payload['subtitle']);

        return $this->ok($payload);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->keepActiveFlag($request, (bool) $client->is_active);
        $client->update($this->validated($request));

        return $this->ok($client->fresh(), 'تم تحديث بيانات الزبون.');
    }
}
