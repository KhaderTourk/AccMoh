<?php

namespace App\Http\Controllers\Cp;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Currency;
use App\Models\LedgerEntry;
use App\Support\DateRange;
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
                ->when($request->transaction_type, fn ($qq, $t) => $qq->where('transaction_type', $t))
                ->tap(fn ($qq) => DateRange::constrain($qq, 'occurred_on', $from, $to))
                ->when($request->q, fn ($qq, $term) => $qq->where(function ($inner) use ($term) {
                    $inner->where('description', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                }));
        };

        $entries = LedgerEntry::query()
            ->with(['fund', 'paymentMethod', 'currency'])
            ->tap($apply)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $totals = LedgerEntry::query()
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
            'types' => TransactionType::cases(),
        ] + $this->financeLookups());
    }
}
