<?php

namespace App\Http\Controllers\Cp;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\CashPayment;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\FundTransfer;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\DateRange;
use App\Support\TableSort;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $apply = function ($q) use ($request, $from, $to) {
            $q->when($request->fund_id, fn ($qq, $id) => $qq->where('fund_id', $id))
                ->when($request->currency_id, fn ($qq, $id) => $qq->where('currency_id', $id))
                ->when($request->payment_method_id, fn ($qq, $id) => $qq->where('payment_method_id', $id))
                ->when($request->transaction_type, function ($qq, $t) {
                    $type = TransactionType::tryFrom($t);
                    if ($type?->isIncomingCash()) {
                        $qq->whereIn('transaction_type', collect(TransactionType::cases())->filter->isIncomingCash()->map->value);
                    } elseif ($type?->isOutgoingCash()) {
                        $qq->whereIn('transaction_type', collect(TransactionType::cases())->filter->isOutgoingCash()->map->value);
                    } else {
                        $qq->where('transaction_type', $t);
                    }
                })
                ->tap(fn ($qq) => DateRange::constrain($qq, 'occurred_on', $from, $to))
                ->when($request->q, fn ($qq, $term) => $qq->where(function ($inner) use ($term) {
                    $inner->where('description', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                }));
        };

        $entries = TableSort::paginate(
            LedgerEntry::query()
                ->withoutReversedPayments()
                ->with([
                    'fund',
                    'paymentMethod',
                    'currency',
                    'creator',
                    'related' => function ($morphTo) {
                        $morphTo->morphWith([
                            CashPayment::class => ['party'],
                            FundTransfer::class => ['fund'],
                        ]);
                    },
                ])
                ->tap($apply),
            [
                'date' => ['column' => 'occurred_on', 'dir' => 'desc'],
                'time' => ['column' => 'created_at', 'dir' => 'desc'],
                'type' => ['column' => 'transaction_type'],
                'description' => ['column' => 'description'],
                'party' => ['value' => fn (LedgerEntry $entry) => $entry->counterpartyLabel() ?? ''],
                'fund' => ['query' => fn () => Fund::query()->select('name')->whereColumn('funds.id', 'ledger_entries.fund_id')],
                'method' => ['query' => fn () => PaymentMethod::query()->select('name')->whereColumn('payment_methods.id', 'ledger_entries.payment_method_id')],
                'amount' => ['column' => 'amount', 'dir' => 'desc'],
                'user' => ['query' => fn () => User::query()->select('name')->whereColumn('users.id', 'ledger_entries.created_by')],
            ],
            'date',
            40
        );

        $totals = LedgerEntry::query()
            ->withoutReversedPayments()
            ->tap($apply)
            ->selectRaw('currency_id, SUM(amount) as total')
            ->groupBy('currency_id')
            ->get()
            ->map(function ($row) {
                $currency = Currency::query()->find($row->currency_id);

                return [
                    'currency' => $currency,
                    'total' => $row->total,
                ];
            });

        return view('cp.finance.ledger.index', [
            'entries' => $entries,
            'totals' => $totals,
            'types' => [
                TransactionType::IncomingPayment,
                TransactionType::OutgoingPayment,
                TransactionType::TransferOut,
                TransactionType::TransferIn,
                TransactionType::TransferFee,
                TransactionType::Adjustment,
                TransactionType::Reversal,
            ],
        ] + $this->financeLookups());
    }
}
