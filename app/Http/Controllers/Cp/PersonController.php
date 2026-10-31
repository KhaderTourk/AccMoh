<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Person;
use App\Services\Export\PdfExporter;
use App\Services\Finance\PartyPageService;
use App\Support\Money;
use App\Support\PartyStatement;
use App\Support\Phone;
use App\Support\TableSort;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        $currencies = Currency::query()->active()->get();
        $rows = Person::query()->orderBy('name')->get()->map(function (Person $p) use ($currencies) {
            $balances = [];
            foreach ($currencies as $currency) {
                $v = $p->netAmount($currency->id);
                if (! Money::isZero($v)) {
                    $balances[] = ['code' => $currency->code, 'amount' => (float) $v, 'label' => $currency->format(Money::abs($v))];
                }
            }

            return [
                'id' => $p->id,
                'name' => $p->name,
                'sub' => (string) $p->relationship,
                'phone' => (string) $p->phone,
                'notes' => (string) $p->notes,
                'archived' => ! $p->is_active,
                'balances' => $balances,
                'fields' => ['detail' => (string) $p->relationship],
                'show' => route('cp.persons.show', $p),
                'pdf' => route('cp.persons.export-pdf', $p),
                'update' => route('cp.persons.update', $p),
                'archive' => route('cp.persons.destroy', $p),
                'restore' => route('cp.persons.restore', $p),
            ];
        })->values();

        return view('cp.finance.parties.index', [
            'party' => self::profile(),
            'rows' => $rows,
            'flash' => (int) $request->query('flash'),
            'storeUrl' => route('cp.persons.store'),
        ]);
    }

    public function create()
    {
        return view('cp.finance.persons.form', ['member' => new Person(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $member = Person::query()->create($this->validated($request) + ['is_active' => true]);

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.persons.index', ['flash' => $member->id])->with('success', 'تم إضافة الشخص.');
        }

        return redirect()->route('cp.persons.show', $member)->with('success', 'تم إضافة الشخص.');
    }

    public function show(Person $person)
    {
        $pages = app(PartyPageService::class);
        return view('cp.finance.parties.show', [
            'party' => self::profile(),
            'model' => $person,
            'title' => $person->name,
            'subtitle' => trim(implode(' · ', array_filter([$person->relationship, $person->phone]))),
            'movements' => $pages->movements($person),
            'lookups' => [
                'currencies' => Currency::query()->active()->get(['id', 'code', 'symbol', 'name']),
                'methods' => \App\Models\PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
                'fundId' => \App\Models\Fund::family()->id,
                'salary' => null,
                'partyKey' => 'person:'.$person->id,
                'targets' => $pages->transferTargets($person),
            ],
            'urls' => [
                'give' => route('cp.payments.store', ['outgoing']),
                'take' => route('cp.payments.store', ['incoming']),
                'goods' => route('cp.client-goods-takes.store'),
                'memo' => route('cp.balance-memos.store'),
                'transfer' => route('cp.balance-memos.transfer.store'),
                'pdf' => route('cp.persons.export-pdf', $person),
                'index' => route('cp.persons.index'),
                'edit' => route('cp.persons.edit', $person),
                'archive' => $person->is_active ? route('cp.persons.destroy', $person) : route('cp.persons.restore', $person),
            ],
        ]);
    }

    public function exportPdf(Request $request, Person $person, PdfExporter $pdf)
    {
        $pages = app(PartyPageService::class);
        return $pdf->download(
            'cp.finance.parties.print',
            PartyStatement::data($request, self::profile(), $person->name,
                trim(implode(' · ', array_filter([$person->relationship, $person->phone]))), $pages->movements($person)),
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

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.persons.index', ['flash' => $person->id])->with('success', 'انحفظ التعديل.');
        }

        return redirect()->route('cp.persons.show', $person)->with('success', 'تم التحديث.');
    }

    public function destroy(Person $person)
    {
        $person->update(['is_active' => false]);

        return redirect()->route('cp.persons.index')
            ->with('success', 'انأرشف، وبتلاقيه مع كل حركاته بتاب «مؤرشف».');
    }

    public function restore(Person $person)
    {
        $person->update(['is_active' => true]);

        return redirect()->route('cp.persons.index', ['flash' => $person->id])->with('success', 'رجع للقائمة.');
    }

    /** @return array<string, mixed> */
    public static function profile(): array
    {
        return [
            'kind' => 'person',
            'noun' => 'شخص',
            'plural' => 'الأشخاص',
            'pos' => 'بدي منه', 'neg' => 'إله عندي',
            'posTab' => 'بدي منهم', 'negTab' => 'إلهم عندي',
            'g1' => 'أعطيته', 'g2' => 'أخدت منه',
            'detail' => 'الصلة',
            'legal' => false,
        ];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => Phone::rules(),
            'notes' => ['nullable', 'string'],
        ], ['phone.regex' => Phone::message()]);
        if ($request->has('detail')) {
            $data['relationship'] = $request->input('detail');
        }
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

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
