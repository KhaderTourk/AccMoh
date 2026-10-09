<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Person;
use App\Services\Export\PdfExporter;
use App\Support\Phone;
use App\Support\TableSort;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        $currencies = Currency::query()->active()->get();

        $members = TableSort::paginate(
            Person::query()
                ->active()
                ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                        ->orWhere('relationship', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                })),
            [
                'name' => ['column' => 'name'],
                'relationship' => ['column' => 'relationship'],
                'phone' => ['column' => 'phone'],
                'net' => [
                    'dir' => 'desc',
                    'value' => function (Person $member) use ($currencies) {
                        $sum = 0.0;
                        foreach ($currencies as $currency) {
                            $sum += (float) $member->netAmount($currency->id);
                        }

                        return $sum;
                    },
                ],
            ],
            'name'
        );

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
        $person->update(['is_active' => false]);

        return redirect()->route('cp.persons.index')
            ->with('success', 'تم أرشفة الشخص ولن يظهر في القوائم.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => Phone::rules(),
            'notes' => ['nullable', 'string'],
        ], ['phone.regex' => Phone::message()]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function showPayload(Person $person): array
    {
        $person->load([
            'cashPayments' => fn ($q) => $q->active()->with(['currency', 'fxCurrency', 'paymentMethod', 'fund'])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id'),
            'goodsTakes' => fn ($q) => $q->with(['currency', 'fxCurrency'])
                ->orderByDesc('taken_on')
                ->orderByDesc('id'),
            'balanceMemos' => fn ($q) => $q->with('currency')
                ->orderByDesc('memo_date')
                ->orderByDesc('id'),
        ]);

        return [
            'member' => $person,
            'currencies' => Currency::query()->active()->get(),
            'exportedAt' => format_date(now(), true),
            'title' => $person->name,
            'subtitle' => trim(implode(' · ', array_filter([$person->relationship, $person->phone]))),
        ];
    }
}
