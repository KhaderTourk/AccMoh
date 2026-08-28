<?php

namespace App\Http\Controllers\Cp;

use App\Enums\LoanDirection;
use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\FamilyLoan;
use App\Models\FamilyMember;
use App\Services\Finance\FamilyLoanService;
use App\Services\Finance\LoanRepaymentService;
use App\Services\Finance\ReversalService;
use App\Support\Money;
use Illuminate\Http\Request;

class FamilyLoanController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        $loans = FamilyLoan::query()
            ->with(['familyMember', 'currency', 'paymentMethod'])
            ->when($request->family_member_id, fn ($q, $id) => $q->where('family_member_id', $id))
            ->when($request->direction, fn ($q, $d) => $q->where('direction', $d))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->boolean('open_only'), fn ($q) => $q->active()->whereIn('status', ['open', 'partial']))
            ->orderByDesc('loan_date')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.loans.index', [
            'loans' => $loans,
            'members' => FamilyMember::query()->orderBy('name')->get(),
            'directions' => LoanDirection::cases(),
        ] + $this->financeLookups());
    }

    public function create(Request $request)
    {
        return view('cp.finance.loans.form', [
            'members' => FamilyMember::query()->active()->orderBy('name')->get(),
            'directions' => LoanDirection::cases(),
            'selectedMemberId' => $request->family_member_id,
            'selectedDirection' => $request->direction ?? LoanDirection::Borrowed->value,
        ] + $this->financeLookups());
    }

    public function store(Request $request, FamilyLoanService $service)
    {
        $data = $request->validate([
            'family_member_id' => ['required', 'exists:family_members,id'],
            'direction' => ['required', 'in:borrowed,lent'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'loan_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $loan = $service->create($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.family-members.show', $loan->family_member_id)
            ->with('success', 'تم تسجيل الحركة.');
    }

    public function createRepayment(Request $request)
    {
        return view('cp.finance.loans.repay', [
            'members' => FamilyMember::query()->active()->orderBy('name')->get(),
            'directions' => LoanDirection::cases(),
            'selectedMemberId' => $request->family_member_id,
            'selectedDirection' => $request->direction ?? LoanDirection::Borrowed->value,
        ] + $this->financeLookups());
    }

    public function storeRepayment(Request $request, LoanRepaymentService $service)
    {
        $data = $request->validate([
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
        ]);

        $allocations = collect($data['allocations'] ?? [])->map(fn ($row) => [
            'family_loan_id' => (int) $row['family_loan_id'],
            'amount' => $row['amount'] ?? 0,
        ])->all();

        try {
            $repayment = $service->repay($data, $allocations);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.family-members.show', $repayment->family_member_id)
            ->with('success', 'تم تسجيل التسوية.');
    }

    public function reverse(FamilyLoan $loan, ReversalService $reversals)
    {
        try {
            $reversals->reverseLoan($loan);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم إلغاء الحركة.');
    }

    public function reverseRepayment(\App\Models\FamilyLoanRepayment $repayment, ReversalService $reversals)
    {
        try {
            $reversals->reverseRepayment($repayment);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم إلغاء التسوية.');
    }

    public function openLoans(FamilyMember $family_member, Request $request)
    {
        $currencyId = (int) $request->currency_id;
        $direction = $request->direction;

        $loans = FamilyLoan::query()
            ->active()
            ->where('family_member_id', $family_member->id)
            ->when($currencyId, fn ($q) => $q->where('currency_id', $currencyId))
            ->when($direction, fn ($q) => $q->where('direction', $direction))
            ->whereIn('status', ['open', 'partial'])
            ->with('currency')
            ->orderBy('loan_date')
            ->get()
            ->filter(fn ($l) => Money::isPositive($l->remainingAmount()))
            ->values()
            ->map(fn ($l) => [
                'id' => $l->id,
                'direction' => $l->direction->value,
                'amount' => $l->amount,
                'remaining' => $l->remainingAmount(),
                'currency_id' => $l->currency_id,
                'loan_date' => $l->loan_date->format('Y-m-d'),
                'notes' => $l->notes,
            ]);

        return response()->json(['loans' => $loans]);
    }
}
