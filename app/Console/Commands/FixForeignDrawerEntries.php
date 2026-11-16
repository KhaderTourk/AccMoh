<?php

namespace App\Console\Commands;

use App\Models\CashPayment;
use App\Models\Currency;
use App\Models\FinancialAuditLog;
use App\Models\LedgerEntry;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 16/11/2026: a payment entered in USD/JOD used to hit the drawer in ILS (5 $ at 3.7 took
 * 18.50 ₪ out of the ILS drawer). This moves each such drawer line to the currency that was
 * really paid (5 $ out of the USD drawer). Party balances are untouched: they read the
 * payment's ILS amount, not the drawer line.
 */
class FixForeignDrawerEntries extends Command
{
    protected $signature = 'drawers:fix-foreign-payments {--apply : Save the change (without it, only report)}';

    protected $description = 'Move drawer lines of USD/JOD payments from the ILS drawer to the real currency paid';

    public function handle(): int
    {
        $ils = Currency::withoutGlobalScopes()->where('code', 'ILS')->pluck('id')->all();
        $payments = CashPayment::withoutGlobalScopes()
            ->whereNotNull('fx_currency_id')
            ->whereNotNull('source_amount')
            ->orderBy('id')
            ->get();

        $fixed = 0;
        foreach ($payments as $payment) {
            TenantContext::set((int) $payment->tenant_id);
            $entries = LedgerEntry::withoutGlobalScopes()
                ->where('related_type', CashPayment::class)
                ->where('related_id', $payment->id)
                ->whereIn('currency_id', $ils)
                ->get();

            foreach ($entries as $entry) {
                // Only a line that carries exactly this payment's ILS amount.
                if (Money::cmp(Money::abs($entry->amount), Money::of($payment->amount)) !== 0) {
                    $this->warn("Payment #{$payment->id}: ledger #{$entry->id} amount {$entry->amount} ≠ {$payment->amount}, skipped.");

                    continue;
                }

                $new = Money::isNegative($entry->amount) ? Money::neg($payment->source_amount) : Money::of($payment->source_amount);
                $code = Currency::withoutGlobalScopes()->find($payment->fx_currency_id)?->code;
                $this->line(sprintf(
                    'Payment #%d «%s» %s: ledger #%d %s ILS → %s %s%s',
                    $payment->id, $payment->name, $payment->occurred_on?->format('j/n/Y'), $entry->id,
                    $entry->amount, $new, $code, $payment->is_reversed ? ' (cancelled pair)' : ''
                ));

                if ($this->option('apply')) {
                    DB::transaction(function () use ($entry, $payment, $new) {
                        $before = ['currency_id' => $entry->currency_id, 'amount' => (string) $entry->amount];
                        $entry->forceFill(['currency_id' => $payment->fx_currency_id, 'amount' => $new])->save();
                        FinancialAuditLog::record('updated', $payment, [
                            'drawer_fix' => 'foreign payment moved to its real currency',
                            'ledger_entry_id' => $entry->id,
                            'before' => $before,
                            'after' => ['currency_id' => $payment->fx_currency_id, 'amount' => $new],
                        ]);
                    });
                }
                $fixed++;
            }
        }

        $this->info($this->option('apply')
            ? "Fixed {$fixed} drawer line(s)."
            : "{$fixed} drawer line(s) would change. Run again with --apply to save.");

        return self::SUCCESS;
    }
}
