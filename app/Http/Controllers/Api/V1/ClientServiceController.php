<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\ClientServiceController as CpClientServiceController;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\ServiceType;
use App\Services\Finance\ClientWorkService;
use App\Support\ArchiveVisibility;
use App\Support\DateRange;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientServiceController extends CpClientServiceController
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        return response()->json(TableSort::paginate(
            ClientService::query()
                ->with(['client', 'currency', 'fxCurrency', 'serviceType'])
                ->when(! ArchiveVisibility::showing(), function ($q) {
                    $q->whereHas('client', fn ($client) => $client->where('is_active', true));
                })
                ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
                ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
                ->tap(fn ($q) => DateRange::constrain($q, 'service_date', $from, $to))
                ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                    $qq->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                })),
            [
                'client' => ['query' => fn () => Client::query()->select('name')->whereColumn('clients.id', 'client_services.client_id')],
                'type' => ['query' => fn () => ServiceType::query()->select('name')->whereColumn('service_types.id', 'client_services.service_type_id')],
                'details' => ['column' => 'title'],
                'amount' => ['column' => 'amount', 'dir' => 'desc'],
                'date' => ['column' => 'service_date', 'dir' => 'desc'],
            ],
            'date'
        ));
    }

    public function store(Request $request, ClientWorkService $service): JsonResponse
    {
        $data = $this->validated($request);
        $row = $this->attempt(fn () => $service->create($data));

        return $this->created($row->load(['client', 'currency', 'fxCurrency', 'serviceType']), 'تم تسجيل الخدمة.');
    }

    public function show(ClientService $clientService): JsonResponse
    {
        return $this->ok($clientService->load(['client', 'currency', 'fxCurrency', 'serviceType']));
    }

    public function update(Request $request, ClientService $clientService, ClientWorkService $service): JsonResponse
    {
        $data = $this->validated($request);
        $this->attempt(fn () => $service->update($clientService, $data));

        return $this->ok($clientService->fresh(['client', 'currency', 'fxCurrency', 'serviceType']), 'تم تحديث الخدمة.');
    }
}
