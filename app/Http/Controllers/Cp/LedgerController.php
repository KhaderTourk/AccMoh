<?php

namespace App\Http\Controllers\Cp;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        $entries = LedgerEntry::query()
            ->with(['fund', 'paymentMethod', 'currency'])
            ->when($request->fund_id, fn ($q, $id) => $q->where('fund_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->payment_method_id, fn ($q, $id) => $q->where('payment_method_id', $id))
            ->when($request->transaction_type, fn ($q, $t) => $q->where('transaction_type', $t))
            ->when($request->from, fn ($q, $d) => $q->whereDate('occurred_on', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('occurred_on', '<=', $d))
            ->when($request->q, fn ($q, $term) => $q->where('description', 'like', "%{$term}%"))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('cp.finance.ledger.index', [
            'entries' => $entries,
            'types' => TransactionType::cases(),
        ] + $this->financeLookups());
    }
}
