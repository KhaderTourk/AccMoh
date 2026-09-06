<?php

namespace App\Services\Api;

use App\Enums\PaymentDirection;
use App\Exceptions\FinanceException;
use App\Models\CashPayment;
use App\Models\SyncOperation;
use App\Services\Finance\CashPaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncOperationService
{
    public const TYPE_INCOMING = 'incoming_payment';
    public const TYPE_OUTGOING = 'outgoing_payment';

    public function __construct(
        protected CashPaymentService $payments,
    ) {}

    /**
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

            if ($existing->status === 'failed') {
                $existing->delete();
            } else {
                throw new FinanceException('العملية ما زالت قيد المعالجة. أعد المحاولة بعد لحظات.');
            }
        }

        try {
            return DB::transaction(function () use ($operationId, $type, $payload, $clientTimestamp, $deviceId) {
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
        $direction = match ($type) {
            self::TYPE_INCOMING => PaymentDirection::Incoming,
            self::TYPE_OUTGOING => PaymentDirection::Outgoing,
            default => throw new FinanceException('نوع العملية غير مدعوم: '.$type),
        };

        $payload['direction'] = $payload['direction'] ?? $direction;
        if (empty($payload['party_type']) || empty($payload['party_id'])) {
            if (! empty($payload['client_id'])) {
                $payload['party_type'] = 'client';
                $payload['party_id'] = $payload['client_id'];
            } elseif (! empty($payload['person_id'])) {
                $payload['party_type'] = 'person';
                $payload['party_id'] = $payload['person_id'];
            } elseif (! empty($payload['vendor_id'])) {
                $payload['party_type'] = 'vendor';
                $payload['party_id'] = $payload['vendor_id'];
            }
        }
        $payment = $this->payments->record($payload);

        return [$payment, $this->paymentPayload($payment)];
    }

    public function paymentPayload(CashPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'direction' => $payment->direction->value,
            'name' => $payment->name,
            'party_type' => $payment->party_type,
            'party_id' => $payment->party_id,
            'amount' => (string) $payment->amount,
            'currency_id' => $payment->currency_id,
            'currency_code' => $payment->currency?->code,
            'payment_method_id' => $payment->payment_method_id,
            'payment_method' => $payment->paymentMethod?->name,
            'fund_id' => $payment->fund_id,
            'occurred_on' => optional($payment->occurred_on)->format('Y-m-d'),
            'notes' => $payment->notes,
        ];
    }
}
