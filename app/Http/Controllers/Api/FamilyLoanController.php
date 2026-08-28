<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Models\FamilyLoan;
use App\Models\FamilyMember;
use App\Services\Api\SyncOperationService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyLoanController extends Controller
{
    public function __construct(protected SyncOperationService $sync) {}

    public function storeLoan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'family_member_id' => ['required', 'exists:family_members,id'],
            'direction' => ['required', 'in:borrowed,lent'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'loan_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'client_timestamp' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $result = $this->sync->execute(
                $data['operation_id'],
                SyncOperationService::TYPE_FAMILY_LOAN,
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

    public function storeRepayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'family_member_id' => ['required', 'exists:family_members,id'],
            'direction' => ['required', 'in:borrowed,lent'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'repayment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.family_loan_id' => ['required_with:allocations', 'exists:family_loans,id'],
            'allocations.*.amount' => ['nullable', 'numeric', 'gte:0'],
            'client_timestamp' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $result = $this->sync->execute(
                $data['operation_id'],
                SyncOperationService::TYPE_FAMILY_REPAYMENT,
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

    public function openLoans(FamilyMember $familyMember, Request $request): JsonResponse
    {
        $currencyId = (int) $request->query('currency_id');
        $direction = $request->query('direction');

        $loans = FamilyLoan::query()
            ->active()
            ->where('family_member_id', $familyMember->id)
            ->when($currencyId, fn ($q) => $q->where('currency_id', $currencyId))
            ->when($direction, fn ($q) => $q->where('direction', $direction))
            ->whereIn('status', ['open', 'partial'])
            ->with('currency:id,code')
            ->orderBy('loan_date')
            ->get()
            ->filter(fn ($l) => Money::isPositive($l->remainingAmount()))
            ->values()
            ->map(fn ($l) => [
                'id' => $l->id,
                'direction' => $l->direction->value ?? $l->direction,
                'amount' => (string) $l->amount,
                'remaining' => $l->remainingAmount(),
                'currency_id' => $l->currency_id,
                'currency_code' => $l->currency?->code,
                'loan_date' => optional($l->loan_date)->format('Y-m-d'),
            ]);

        return response()->json(['data' => $loans]);
    }
}
