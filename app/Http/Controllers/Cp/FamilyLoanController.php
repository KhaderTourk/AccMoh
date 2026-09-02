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
use App\Support\DateRange;
use App\Support\IlsFx;
use App\Support\Money;
use Illuminate\Http\Request;

class FamilyLoanController extends Controller
{
    use LoadsFinanceLookups;

    public function index()
    {
        return redirect()->route('cp.family-loans.debtors');
    }

    public function debtors(Request $request)
    {
        return $this->listing($request, LoanDirection::Lent);
    }

    public function creditors(Request $request)
    {
        return $this->listing($request, LoanDirection::Borrowed);
    }

    public function create(Request $request)
    {
        $direction = LoanDirection::tryFrom((string) $request->direction) ?? LoanDirection::Lent;

        return view('cp.finance.loans.form', [
            'loan' => new FamilyLoan([
                'direction' => $direction,
                'family_member_id' => $request->family_member_id,
                'loan_date' => now()->toDateString(),
            ]),
            'members' => FamilyMember::query()->active()->orderBy('name')->get(),
            'selectedMemberId' => $request->family_member_id,
            'selectedDirection' => $direction->value,
        ] + $this->financeLookups());
    }

    public function store(Request $request, FamilyLoanService $service)
    {
        $isFx = $request->boolean('requires_fx');
        $data = $request->validate([
            'family_member_id' => ['required', 'exists:family_members,id'],
            'direction' => ['required', 'in:borrowed,lent'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'loan_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ] + IlsFx::rules($isFx));
        $data = IlsFx::stamp($data, $isFx);

        try {
            $loan = $service->create($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($this->indexRoute($loan->direction))
            ->with('success', 'تم تسجيل الحركة.');
    }

    public function edit(FamilyLoan $loan)
    {
        if (! $loan->canEdit()) {
            return redirect()->route($this->indexRoute($loan->direction))
                ->with('error', 'لا يمكن تعديل حركة ملغاة أو عليها تسوية.');
        }

        $loan->load(['currency', 'fxCurrency']);

        return view('cp.finance.loans.form', [
            'loan' => $loan,
            'members' => FamilyMember::query()->active()->orderBy('name')->get(),
            'selectedMemberId' => $loan->family_member_id,
            'selectedDirection' => $loan->direction->value,
        ] + $this->financeLookups());
    }

    public function update(Request $request, FamilyLoan $loan, FamilyLoanService $service)
    {
        $isFx = $request->boolean('requires_fx');
        $data = $request->validate([
            'family_member_id' => ['required', 'exists:family_members,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'loan_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ] + IlsFx::rules($isFx));
        $data = IlsFx::stamp($data, $isFx);
        $data['direction'] = $loan->direction->value;

        try {
            $loan = $service->update($loan, $data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($this->indexRoute($loan->direction))
            ->with('success', 'تم تحديث الحركة.');
    }

    public function destroy(FamilyLoan $loan, ReversalService $reversals)
    {
        try {
            $reversals->reverseLoan($loan);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم حذف الحركة وإلغاء أثرها المالي.');
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
        $isFx = $request->boolean('requires_fx');
        $data = $request->validate([
            'family_member_id' => ['required', 'exists:family_members,id'],
            'direction' => ['required', 'in:borrowed,lent'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'repayment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.family_loan_id' => ['required_with:allocations', 'exists:family_loans,id'],
            'allocations.*.amount' => ['nullable', 'numeric', 'gte:0'],
        ] + IlsFx::rules($isFx));
        $data = IlsFx::stamp($data, $isFx);

        $allocations = collect($data['allocations'] ?? [])->map(fn ($row) => [
            'family_loan_id' => (int) $row['family_loan_id'],
            'amount' => $row['amount'] ?? 0,
        ])->all();

        try {
            $repayment = $service->repay($data, $allocations);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($this->indexRoute($repayment->direction))
            ->with('success', 'تم تسجيل التسوية.');
    }

    public function reverse(FamilyLoan $loan, ReversalService $reversals)
    {
        return $this->destroy($loan, $reversals);
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

    protected function listing(Request $request, LoanDirection $direction)
    {
        [$from, $to] = $this->dateRange($request);

        $loans = FamilyLoan::query()
            ->with(['familyMember', 'currency', 'fxCurrency', 'paymentMethod'])
            ->where('direction', $direction)
            ->when($request->family_member_id, fn ($q, $id) => $q->where('family_member_id', $id))
            ->tap(fn ($q) => DateRange::constrain($q, 'loan_date', $from, $to))
            ->when($request->q, fn ($q, $term) => $q->where('notes', 'like', "%{$term}%"))
            ->when($request->boolean('open_only'), fn ($q) => $q->active()->whereIn('status', ['open', 'partial']))
            ->orderByDesc('loan_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.loans.index', [
            'loans' => $loans,
            'direction' => $direction,
            'members' => FamilyMember::query()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    protected function indexRoute(LoanDirection $direction): string
    {
        return $direction === LoanDirection::Lent
            ? 'cp.family-loans.debtors'
            : 'cp.family-loans.creditors';
    }
}
