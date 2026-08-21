<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Services\Api\SyncOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(protected SyncOperationService $sync) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'fund_id' => ['required', 'exists:funds,id'],
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'expense_date' => ['required', 'date'],
            'payee' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'client_timestamp' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $result = $this->sync->execute(
                $data['operation_id'],
                SyncOperationService::TYPE_EXPENSE,
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
}
