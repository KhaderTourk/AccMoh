<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentDirection;
use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Services\Api\SyncOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashPaymentController extends Controller
{
    public function __construct(protected SyncOperationService $sync) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'direction' => ['required', Rule::in(['incoming', 'outgoing'])],
            'name' => ['required', 'string', 'max:255'],
            'fund_id' => ['required', 'exists:funds,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'source_amount' => ['nullable', 'numeric', 'gt:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'occurred_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'party_type' => ['nullable', Rule::in(['client', 'person', 'vendor'])],
            'party_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'person_id' => ['nullable', 'integer', 'exists:family_members,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'client_timestamp' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = collect($data)->except(['operation_id', 'client_timestamp', 'device_id'])->all();
        if (empty($payload['party_type']) || empty($payload['party_id'])) {
            if (! empty($data['client_id'])) {
                $payload['party_type'] = 'client';
                $payload['party_id'] = $data['client_id'];
            } elseif (! empty($data['person_id'])) {
                $payload['party_type'] = 'person';
                $payload['party_id'] = $data['person_id'];
            } elseif (! empty($data['vendor_id'])) {
                $payload['party_type'] = 'vendor';
                $payload['party_id'] = $data['vendor_id'];
            }
        }

        $type = $data['direction'] === PaymentDirection::Outgoing->value
            ? SyncOperationService::TYPE_OUTGOING
            : SyncOperationService::TYPE_INCOMING;

        try {
            $result = $this->sync->execute(
                $data['operation_id'],
                $type,
                $payload,
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
}
