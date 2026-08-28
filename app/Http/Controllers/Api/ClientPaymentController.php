<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientService;
use App\Services\Api\SyncOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    public function __construct(protected SyncOperationService $sync) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'client_id' => ['required', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'client_timestamp' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $result = $this->sync->execute(
                $data['operation_id'],
                SyncOperationService::TYPE_CLIENT_PAYMENT,
                collect($data)->except(['operation_id', 'client_timestamp', 'device_id'])->all(),
                $data['client_timestamp'] ?? null,
                $data['device_id'] ?? $request->header('X-Device-Id')
            );
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'replayed' => $result['replayed'],
            'operation_id' => $data['operation_id'],
            'data' => $result['data'],
        ], $result['replayed'] ? 200 : 201);
    }

    public function unpaidServices(Client $client, Request $request): JsonResponse
    {
        $currencyId = (int) $request->query('currency_id');
        $currency = $currencyId ? \App\Models\Currency::query()->find($currencyId) : null;
        $outstanding = $currencyId ? $client->outstandingAmount($currencyId) : '0.00';

        $services = ClientService::query()
            ->billable()
            ->where('client_id', $client->id)
            ->when($currencyId, fn ($q) => $q->where('currency_id', $currencyId))
            ->with('currency:id,code,symbol')
            ->orderByDesc('service_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'amount' => (string) $s->amount,
                'currency_id' => $s->currency_id,
                'currency_code' => $s->currency?->code,
                'service_date' => optional($s->service_date)->format('Y-m-d'),
            ]);

        return response()->json([
            'outstanding' => $outstanding,
            'outstanding_formatted' => $currency?->format($outstanding) ?? $outstanding,
            'data' => $services,
        ]);
    }
}
