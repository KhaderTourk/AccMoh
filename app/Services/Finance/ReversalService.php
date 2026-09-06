<?php

namespace App\Services\Finance;

use App\Exceptions\FinanceException;
use App\Models\CashPayment;
use App\Models\FinancialAuditLog;
use App\Models\FundTransfer;
use Illuminate\Support\Facades\DB;

class ReversalService
{
    public function __construct(
        protected FinancialLedgerService $ledger,
        protected CashPaymentService $payments,
    ) {}

    public function reverseCashPayment(CashPayment $payment, $occurredOn = null): void
    {
        $this->payments->reverse($payment, $occurredOn);
    }

    public function reverseTransfer(FundTransfer $transfer, $occurredOn = null): void
    {
        if ($transfer->is_reversed) {
            throw new FinanceException('هذا التحويل ملغى مسبقاً.');
        }

        DB::transaction(function () use ($transfer, $occurredOn) {
            $this->ledger->reverseGroup(
                $transfer->ledger_group_id,
                $transfer,
                'إلغاء تحويل بين طرق الدفع',
                $occurredOn ?? now()->toDateString()
            );
            $transfer->update([
                'is_reversed' => true,
                'reversed_at' => now(),
            ]);
            FinancialAuditLog::record('reversed', $transfer);
        });
    }
}
