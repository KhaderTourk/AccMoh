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
            'operations.*.type' => ['required', 'in:client_payment,expense,family_loan,family_loan_repayment'],
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
            SyncOperationService::TYPE_CLIENT_PAYMENT => [
                'client_id' => ['required', 'integer', 'exists:clients,id'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'currency_id' => ['required', 'integer', 'exists:currencies,id'],
                'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
                'payment_date' => ['required', 'date'],
                'payer_name' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
            ],
            SyncOperationService::TYPE_EXPENSE => [
                'fund_id' => ['required', 'integer', 'exists:funds,id'],
                'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
                'description' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'currency_id' => ['required', 'integer', 'exists:currencies,id'],
                'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
                'expense_date' => ['required', 'date'],
                'payee' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
            ],
            SyncOperationService::TYPE_FAMILY_LOAN => [
                'family_member_id' => ['required', 'integer', 'exists:family_members,id'],
                'direction' => ['required', 'in:borrowed,lent'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'currency_id' => ['required', 'integer', 'exists:currencies,id'],
                'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
                'loan_date' => ['required', 'date'],
                'notes' => ['nullable', 'string'],
            ],
            SyncOperationService::TYPE_FAMILY_REPAYMENT => [
                'family_member_id' => ['required', 'integer', 'exists:family_members,id'],
                'direction' => ['required', 'in:borrowed,lent'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'currency_id' => ['required', 'integer', 'exists:currencies,id'],
                'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
                'repayment_date' => ['required', 'date'],
                'notes' => ['nullable', 'string'],
                'allocations' => ['nullable', 'array'],
                'allocations.*.family_loan_id' => ['required_with:allocations', 'integer', 'exists:family_loans,id'],
                'allocations.*.amount' => ['nullable', 'numeric', 'gte:0'],
            ],
            default => [],
        };

        validator($payload, $rules)->validate();
    }
}
