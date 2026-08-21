<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Expense;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\ReversalService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        $expenses = Expense::query()
            ->with(['fund', 'category', 'currency', 'paymentMethod'])
            ->when($request->fund_id, fn ($q, $id) => $q->where('fund_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->payment_method_id, fn ($q, $id) => $q->where('payment_method_id', $id))
            ->when($request->from, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->when($request->q, fn ($q, $term) => $q->where('description', 'like', "%{$term}%"))
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.expenses.index', array_merge(compact('expenses'), $this->financeLookups()));
    }

    public function create()
    {
        return view('cp.finance.expenses.form', $this->financeLookups());
    }

    public function store(Request $request, ExpenseService $service)
    {
        $data = $request->validate([
            'fund_id' => ['required', 'exists:funds,id'],
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'expense_date' => ['required', 'date'],
            'payee' => ['nullable', 'string', 'max:255'],
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
