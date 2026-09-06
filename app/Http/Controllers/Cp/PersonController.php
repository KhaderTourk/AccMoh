<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Person;
use App\Services\Export\PdfExporter;
use App\Support\Phone;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        $members = Person::query()
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('relationship', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $currencies = Currency::query()->active()->get();

        return view('cp.finance.persons.index', compact('members', 'currencies'));
    }

    public function create()
    {
        return view('cp.finance.persons.form', ['member' => new Person(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $member = Person::query()->create($this->validated($request));

        return redirect()->route('cp.persons.show', $member)->with('success', 'تم إضافة الشخص.');
    }

    public function show(Person $person)
    {
        return view('cp.finance.persons.show', $this->showPayload($person));
    }

    public function exportPdf(Person $person, PdfExporter $pdf)
    {
        $data = $this->showPayload($person);
        $data['exporting'] = true;

        return $pdf->download(
            'cp.finance.persons.print',
            $data,
            'person-'.$person->id.'.pdf'
        );
    }

    public function edit(Person $person)
    {
        return view('cp.finance.persons.form', ['member' => $person]);
    }

    public function update(Request $request, Person $person)
    {
        $person->update($this->validated($request));

        return redirect()->route('cp.persons.show', $person)->with('success', 'تم التحديث.');
    }

    public function destroy(Person $person)
    {
        if ($person->hasFinancialHistory()) {
            $person->update(['is_active' => false]);

            return redirect()->route('cp.persons.index')
                ->with('success', 'تم أرشفة الشخص لأنه يملك سجلاً مالياً.');
        }

        $person->forceDelete();

        return redirect()->route('cp.persons.index')->with('success', 'تم حذف الشخص.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => Phone::rules(),
            'notes' => ['nullable', 'string'],
        ], ['phone.regex' => Phone::message()]) + ['is_active' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected function showPayload(Person $person): array
    {
        $person->load([
            'cashPayments' => fn ($q) => $q->with(['currency', 'fxCurrency', 'paymentMethod', 'fund'])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id'),
        ]);

        return [
            'member' => $person,
            'currencies' => Currency::query()->active()->get(),
            'exportedAt' => now()->format('Y-m-d H:i'),
            'title' => $person->name,
            'subtitle' => trim(implode(' · ', array_filter([$person->relationship, $person->phone]))),
        ];
    }
}
