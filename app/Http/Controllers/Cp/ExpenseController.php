<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Expense;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\ReversalService;
use App\Support\DateRange;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $expenses = Expense::query()
            ->with(['fund', 'category', 'currency', 'paymentMethod', 'vendor'])
            ->when($request->fund_id, fn ($q, $id) => $q->where('fund_id', $id))
            ->when($request->vendor_id, fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->payment_method_id, fn ($q, $id) => $q->where('payment_method_id', $id))
            ->tap(fn ($q) => DateRange::constrain($q, 'expense_date', $from, $to))
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('description', 'like', "%{$term}%")
                    ->orWhere('payee', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            }))
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.expenses.index', array_merge(compact('expenses'), $this->financeLookups()));
    }

    public function create(Request $request)
    {
        return view('cp.finance.expenses.form', [
            'selectedVendorId' => $request->vendor_id,
            'selectedFundId' => $request->fund_id,
        ] + $this->financeLookups());
    }

    public function store(Request $request, ExpenseService $service)
    {
        $request->merge([
            'vendor_id' => $request->filled('vendor_id') ? $request->vendor_id : null,
            'expense_category_id' => $request->filled('expense_category_id') ? $request->expense_category_id : null,
        ]);
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'expense_date' => ['required', 'date'],
            'payee' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $service->record($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.expenses.index')->with('success', 'تم تسجيل المصروف.');
    }

    public function reverse(Expense $expense, ReversalService $reversals)
    {
        try {
            $reversals->reverseExpense($expense);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم إلغاء المصروف.');
    }
}
