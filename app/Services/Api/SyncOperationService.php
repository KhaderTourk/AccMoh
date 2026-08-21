<?php

namespace App\Services\Api;

use App\Enums\LoanDirection;
use App\Exceptions\FinanceException;
use App\Models\ClientPayment;
use App\Models\Expense;
use App\Models\FamilyLoan;
use App\Models\FamilyLoanRepayment;
use App\Models\SyncOperation;
use App\Services\Finance\ClientPaymentService;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\FamilyLoanService;
use App\Services\Finance\LoanRepaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncOperationService
{
    public const TYPE_CLIENT_PAYMENT = 'client_payment';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_FAMILY_LOAN = 'family_loan';
    public const TYPE_FAMILY_REPAYMENT = 'family_loan_repayment';

    public function __construct(
        protected ClientPaymentService $payments,
        protected ExpenseService $expenses,
        protected FamilyLoanService $loans,
        protected LoanRepaymentService $repayments,
    ) {}

    /**
     * Execute an idempotent financial operation.
     * Replaying the same operation_id returns the original successful response.
     *
     * @return array{replayed: bool, operation: SyncOperation, data: array}
     */
    public function execute(string $operationId, string $type, array $payload, ?string $clientTimestamp = null, ?string $deviceId = null): array
    {
        $existing = SyncOperation::query()->where('operation_id', $operationId)->first();
        if ($existing) {
            if ($existing->isCompleted()) {
                return [
                    'replayed' => true,
                    'operation' => $existing,
                    'data' => $existing->response_payload ?? [],
                ];
            }

            // Allow retrying a previously failed operation with the same id after fixing payload
            if ($existing->status === 'failed') {
                $existing->delete();
            } else {
                throw new FinanceException('العملية ما زالت قيد المعالجة. أعد المحاولة بعد لحظات.');
            }
        }

        try {
            return DB::transaction(function () use ($operationId, $type, $payload, $clientTimestamp, $deviceId) {
                // Lock uniqueness early
                $operation = SyncOperation::query()->create([
                    'operation_id' => $operationId,
                    'user_id' => Auth::id(),
                    'type' => $type,
                    'status' => 'processing',
                    'request_payload' => $payload,
                    'client_timestamp' => $clientTimestamp,
                    'device_id' => $deviceId,
                ]);

                [$related, $data] = $this->dispatch($type, $payload);

                $operation->update([
                    'status' => 'completed',
                    'response_payload' => $data,
                    'related_type' => $related ? $related::class : null,
                    'related_id' => $related?->getKey(),
                ]);

                return [
                    'replayed' => false,
                    'operation' => $operation->fresh(),
                    'data' => $data,
                ];
            });
        } catch (Throwable $e) {
            SyncOperation::query()->updateOrCreate(
                ['operation_id' => $operationId],
                [
                    'user_id' => Auth::id(),
                    'type' => $type,
                    'status' => 'failed',
                    'request_payload' => $payload,
                    'error_message' => $e->getMessage(),
                    'client_timestamp' => $clientTimestamp,
                    'device_id' => $deviceId,
                ]
            );

            throw $e;
        }
    }

    /**
     * @return array{0: \Illuminate\Database\Eloquent\Model|null, 1: array}
     */
    protected function dispatch(string $type, array $payload): array
    {
        return match ($type) {
            self::TYPE_CLIENT_PAYMENT => $this->handleClientPayment($payload),
            self::TYPE_EXPENSE => $this->handleExpense($payload),
            self::TYPE_FAMILY_LOAN => $this->handleFamilyLoan($payload),
            self::TYPE_FAMILY_REPAYMENT => $this->handleFamilyRepayment($payload),
            default => throw new FinanceException('نوع العملية غير مدعوم: '.$type),
        };
    }

    protected function handleClientPayment(array $payload): array
    {
        $allocations = collect($payload['allocations'] ?? [])->map(fn ($row) => [
            'client_service_id' => (int) $row['client_service_id'],
            'amount' => $row['amount'],
        ])->all();

        $payment = $this->payments->receive($payload, $allocations);
        $payment->load(['client', 'currency', 'paymentMethod', 'allocations.service']);

        return [$payment, $this->paymentPayload($payment)];
    }

    protected function handleExpense(array $payload): array
    {
        $expense = $this->expenses->record($payload);
        $expense->load(['fund', 'category', 'currency', 'paymentMethod']);

        return [$expense, $this->expensePayload($expense)];
    }

    protected function handleFamilyLoan(array $payload): array
    {
        $loan = $this->loans->create($payload);
        $loan->load(['familyMember', 'currency', 'paymentMethod']);

        return [$loan, $this->loanPayload($loan)];
    }

    protected function handleFamilyRepayment(array $payload): array
    {
        $allocations = collect($payload['allocations'] ?? [])->map(fn ($row) => [
            'family_loan_id' => (int) $row['family_loan_id'],
            'amount' => $row['amount'],
        ])->all();

        $repayment = $this->repayments->repay($payload, $allocations);
        $repayment->load(['familyMember', 'currency', 'paymentMethod', 'items.loan']);

        return [$repayment, $this->repaymentPayload($repayment)];
    }

    public function paymentPayload(ClientPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'client_id' => $payment->client_id,
            'client_name' => $payment->client?->name,
            'amount' => (string) $payment->amount,
            'currency_id' => $payment->currency_id,
            'currency_code' => $payment->currency?->code,
            'payment_method_id' => $payment->payment_method_id,
            'payment_method' => $payment->paymentMethod?->name,
            'payer_name' => $payment->payer_name,
            'payment_date' => optional($payment->payment_date)->format('Y-m-d'),
            'notes' => $payment->notes,
            'allocations' => $payment->allocations->map(fn ($a) => [
                'client_service_id' => $a->client_service_id,
                'service_title' => $a->service?->title,
                'allocated_amount' => (string) $a->allocated_amount,
            ])->values()->all(),
        ];
    }

    public function expensePayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'fund_id' => $expense->fund_id,
            'fund' => $expense->fund?->name,
            'expense_category_id' => $expense->expense_category_id,
            'description' => $expense->description,
            'amount' => (string) $expense->amount,
            'currency_id' => $expense->currency_id,
            'currency_code' => $expense->currency?->code,
            'payment_method_id' => $expense->payment_method_id,
            'payment_method' => $expense->paymentMethod?->name,
            'expense_date' => optional($expense->expense_date)->format('Y-m-d'),
            'payee' => $expense->payee,
            'notes' => $expense->notes,
        ];
    }

    public function loanPayload(FamilyLoan $loan): array
    {
        return [
            'id' => $loan->id,
            'family_member_id' => $loan->family_member_id,
            'family_member' => $loan->familyMember?->name,
            'direction' => $loan->direction instanceof LoanDirection ? $loan->direction->value : $loan->direction,
            'amount' => (string) $loan->amount,
            'remaining' => $loan->remainingAmount(),
            'currency_id' => $loan->currency_id,
            'currency_code' => $loan->currency?->code,
            'payment_method_id' => $loan->payment_method_id,
            'payment_method' => $loan->paymentMethod?->name,
            'loan_date' => optional($loan->loan_date)->format('Y-m-d'),
            'status' => $loan->status->value ?? $loan->status,
            'notes' => $loan->notes,
        ];
    }

    public function repaymentPayload(FamilyLoanRepayment $repayment): array
    {
        return [
            'id' => $repayment->id,
            'family_member_id' => $repayment->family_member_id,
            'family_member' => $repayment->familyMember?->name,
            'direction' => $repayment->direction instanceof LoanDirection ? $repayment->direction->value : $repayment->direction,
            'amount' => (string) $repayment->amount,
            'currency_id' => $repayment->currency_id,
            'currency_code' => $repayment->currency?->code,
            'payment_method_id' => $repayment->payment_method_id,
            'payment_method' => $repayment->paymentMethod?->name,
            'repayment_date' => optional($repayment->repayment_date)->format('Y-m-d'),
            'notes' => $repayment->notes,
            'allocations' => $repayment->items->map(fn ($i) => [
                'family_loan_id' => $i->family_loan_id,
                'allocated_amount' => (string) $i->allocated_amount,
                'loan_remaining' => $i->loan?->remainingAmount(),
            ])->values()->all(),
        ];
    }
}
