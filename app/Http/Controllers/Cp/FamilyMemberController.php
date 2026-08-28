<?php

namespace App\Http\Controllers\Cp;

use App\Enums\LoanDirection;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\FamilyMember;
use App\Services\Export\PdfExporter;
use Illuminate\Http\Request;

class FamilyMemberController extends Controller
{
    public function index(Request $request)
    {
        $members = FamilyMember::query()
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('relationship', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $currencies = Currency::query()->active()->get();

        return view('cp.finance.family.index', compact('members', 'currencies'));
    }

    public function create()
    {
        return view('cp.finance.family.form', ['member' => new FamilyMember(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $member = FamilyMember::query()->create($this->validated($request));

        return redirect()->route('cp.family-members.show', $member)->with('success', 'تم إضافة الفرد.');
    }

    public function show(FamilyMember $familyMember)
    {
        return view('cp.finance.family.show', $this->showPayload($familyMember));
    }

    public function exportPdf(FamilyMember $familyMember, PdfExporter $pdf)
    {
        $data = $this->showPayload($familyMember);
        $data['exporting'] = true;

        return $pdf->download(
            'cp.finance.family.print',
            $data,
            'family-'.$familyMember->id.'.pdf'
        );
    }

    public function edit(FamilyMember $familyMember)
    {
        return view('cp.finance.family.form', ['member' => $familyMember]);
    }

    public function update(Request $request, FamilyMember $familyMember)
    {
        $familyMember->update($this->validated($request));

        return redirect()->route('cp.family-members.show', $familyMember)->with('success', 'تم التحديث.');
    }

    public function destroy(FamilyMember $familyMember)
    {
        if ($familyMember->hasFinancialHistory()) {
            $familyMember->update(['is_active' => false]);

            return redirect()->route('cp.family-members.index')
                ->with('success', 'تم أرشفة الفرد لأنه يملك سجلاً مالياً.');
        }

        $familyMember->forceDelete();

        return redirect()->route('cp.family-members.index')->with('success', 'تم الحذف.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    /**
     * @return array{member: FamilyMember, currencies: \Illuminate\Support\Collection, directions: array, exportedAt: string}
     */
    protected function showPayload(FamilyMember $familyMember): array
    {
        $familyMember->load([
            'loans' => fn ($q) => $q->with(['currency', 'fxCurrency', 'paymentMethod'])->latest('loan_date'),
            'repayments' => fn ($q) => $q->with(['currency', 'fxCurrency', 'paymentMethod', 'items.loan'])->latest('repayment_date'),
        ]);

        return [
            'member' => $familyMember,
            'currencies' => Currency::query()->active()->get(),
            'directions' => LoanDirection::cases(),
            'exportedAt' => now()->format('Y-m-d H:i'),
            'title' => $familyMember->name,
        ];
    }
}
