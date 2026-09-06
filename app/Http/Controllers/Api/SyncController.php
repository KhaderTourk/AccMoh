<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Services\Api\OfflineSnapshotBuilder;
use App\Services\Api\SyncOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncController extends Controller
{
    public function __construct(
        protected SyncOperationService $sync,
        protected OfflineSnapshotBuilder $snapshot,
    ) {}

    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:100'],
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.operation_id' => ['required', 'uuid'],
            'operations.*.type' => ['required', 'in:incoming_payment,outgoing_payment'],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.client_timestamp' => ['nullable', 'date'],
        ]);

        $results = [];

        foreach ($data['operations'] as $op) {
            try {
                $this->validatePayloadForType($op['type'], $op['payload']);

                $result = $this->sync->execute(
                    $op['operation_id'],
                    $op['type'],
                    $op['payload'],
                    $op['client_timestamp'] ?? null,
                    $data['device_id'] ?? $request->header('X-Device-Id')
                );

                $results[] = [
                    'operation_id' => $op['operation_id'],
                    'type' => $op['type'],
                    'status' => 'completed',
                    'replayed' => $result['replayed'],
                    'data' => $result['data'],
                ];
            } catch (ValidationException $e) {
                $results[] = [
                    'operation_id' => $op['operation_id'],
                    'type' => $op['type'],
                    'status' => 'failed',
                    'error' => collect($e->errors())->flatten()->first() ?? 'بيانات غير صالحة',
                    'errors' => $e->errors(),
                ];
            } catch (FinanceException $e) {
                $results[] = [
                    'operation_id' => $op['operation_id'],
                    'type' => $op['type'],
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                report($e);
                $results[] = [
                    'operation_id' => $op['operation_id'],
                    'type' => $op['type'],
                    'status' => 'failed',
                    'error' => 'فشل غير متوقع أثناء المزامنة.',
                ];
            }
        }

        return response()->json([
            'results' => $results,
            'snapshot' => $this->snapshot->build(),
        ]);
    }

    protected function validatePayloadForType(string $type, array $payload): void
    {
        $rules = match ($type) {
            SyncOperationService::TYPE_INCOMING, SyncOperationService::TYPE_OUTGOING => [
                'name' => ['required', 'string', 'max:255'],
                'fund_id' => ['required', 'integer', 'exists:funds,id'],
                'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
                'currency_id' => ['required', 'integer', 'exists:currencies,id'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'source_amount' => ['nullable', 'numeric', 'gt:0'],
                'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
                'account_holder_name' => ['nullable', 'string', 'max:255'],
                'occurred_on' => ['required', 'date'],
                'notes' => ['nullable', 'string'],
                'party_type' => ['nullable', 'in:client,person,vendor'],
                'party_id' => ['nullable', 'integer'],
                'client_id' => ['nullable', 'integer', 'exists:clients,id'],
                'person_id' => ['nullable', 'integer', 'exists:family_members,id'],
                'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            ],
            default => [],
        };

        validator($payload, $rules)->validate();
    }
}
