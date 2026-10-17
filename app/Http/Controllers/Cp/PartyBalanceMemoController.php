<?php

namespace App\Http\Controllers\Cp;

use App\Enums\BalanceMemoDirection;
use App\Enums\BalanceMemoReason;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\PartyBalanceMemo;
use App\Models\Person;
use App\Models\Vendor;
use App\Services\Finance\PartyBalanceMemoService;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartyBalanceMemoController extends Controller
{
    use LoadsFinanceLookups;

    public function create(Request $request)
    {
        [$partyType, $party] = $this->resolveRequestedParty($request);

        return view('cp.finance.balance-memos.form', [
            'memo' => new PartyBalanceMemo([
                'party_type' => $partyType,
                'party_id' => $party?->id,
                'memo_date' => now()->toDateString(),
                'direction' => BalanceMemoDirection::OwedToUs,
                'reason' => BalanceMemoReason::Opening,
            ]),
            'parties' => $this->partyOptions($party),
            'transfer' => false,
        ] + $this->financeLookups());
    }

    public function store(Request $request, PartyBalanceMemoService $service)
    {
        $memo = $service->create($this->validated($request));

        return redirect($this->redirectTo($memo))->with('success', 'تم تسجيل الرصيد المرحّل دون تحريك الصندوق.');
    }

    public function transferCreate(Request $request)
    {
        [$partyType, $party] = $this->resolveRequestedParty($request);

        return view('cp.finance.balance-memos.form', [
            'memo' => new PartyBalanceMemo([
                'party_type' => $partyType,
                'party_id' => $party?->id,
                'memo_date' => now()->toDateString(),
                'direction' => BalanceMemoDirection::OwedToUs,
                'reason' => BalanceMemoReason::AccountTransfer,
            ]),
            'parties' => $this->partyOptions($party),
            'transfer' => true,
        ] + $this->financeLookups());
    }

    public function transferStore(Request $request, PartyBalanceMemoService $service)
    {
        $memo = $service->transfer($this->validatedTransfer($request));

        return redirect($this->redirectTo($memo))->with('success', 'تم ترحيل الرصيد بين الحسابين دون تحريك الصندوق.');
    }

    public function edit(PartyBalanceMemo $balance_memo)
    {
        $balance_memo->load('party');

        return view('cp.finance.balance-memos.form', [
            'memo' => $balance_memo,
            'parties' => $this->partyOptions($balance_memo->party),
            'transfer' => $balance_memo->isTransfer(),
        ] + $this->financeLookups());
    }

    public function update(Request $request, PartyBalanceMemo $balance_memo, PartyBalanceMemoService $service)
    {
        $data = $balance_memo->isTransfer()
            ? $this->validatedShared($request)
            : $this->validated($request, $balance_memo);

        $service->update($balance_memo, $data);

        return redirect($this->redirectTo($balance_memo))->with('success', 'تم تحديث الرصيد المرحّل.');
    }

    public function destroy(PartyBalanceMemo $balance_memo, PartyBalanceMemoService $service)
    {
        $redirect = $this->redirectTo($balance_memo);
        $service->delete($balance_memo);

        return redirect($redirect)->with('success', 'تم حذف الرصيد المرحّل.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?PartyBalanceMemo $memo = null): array
    {
        $this->mergeParty($request, 'party_key', 'party_type', 'party_id');
        $data = $request->validate($this->sharedRules() + [
            'party_key' => ['required', 'string'],
            'party_type' => ['required', Rule::in(['client', 'person', 'vendor'])],
            'party_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in([
                BalanceMemoReason::Opening->value,
                BalanceMemoReason::YearRollover->value,
            ])],
        ], [
            'party_key.required' => 'اختر الجهة.',
        ]);

        if ($memo) {
            $data['party_type'] = $memo->party_type;
            $data['party_id'] = $memo->party_id;
        }

        $this->assertParty($data['party_type'], $data['party_id'], 'party_key');
        unset($data['party_key']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedTransfer(Request $request): array
    {
        $this->mergeParty($request, 'source_party_key', 'party_type', 'party_id');
        $this->mergeParty($request, 'target_party_key', 'target_party_type', 'target_party_id');

        $data = $request->validate($this->sharedRules() + [
            'source_party_key' => ['required', 'string'],
            'target_party_key' => ['required', 'string', 'different:source_party_key'],
            'party_type' => ['required', Rule::in(['client', 'person', 'vendor'])],
            'party_id' => ['required', 'integer'],
            'target_party_type' => ['required', Rule::in(['client', 'person', 'vendor'])],
            'target_party_id' => ['required', 'integer'],
        ], [
            'source_party_key.required' => 'اختر الحساب المصدر.',
            'target_party_key.required' => 'اختر الحساب الهدف.',
            'target_party_key.different' => 'اختر حساباً مختلفاً عن المصدر.',
        ]);

        $this->assertParty($data['party_type'], $data['party_id'], 'source_party_key');
        $this->assertParty($data['target_party_type'], $data['target_party_id'], 'target_party_key');
        unset($data['source_party_key'], $data['target_party_key']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedShared(Request $request): array
    {
        return $request->validate($this->sharedRules());
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        return [
            'direction' => ['required', Rule::enum(BalanceMemoDirection::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'memo_date' => DateRange::inputRules(),
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function mergeParty(Request $request, string $key, string $typeField, string $idField): void
    {
        if ($request->filled($key) && preg_match('/^(client|person|vendor):(\d+)$/', (string) $request->input($key), $match)) {
            $request->merge([
                $typeField => $match[1],
                $idField => (int) $match[2],
            ]);
        }
    }

    protected function assertParty(?string $type, mixed $id, string $field): void
    {
        if (! $this->findParty($type, $id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => 'اختر جهة من الزبائن أو الأشخاص أو الموظفين أو الموردين.',
            ]);
        }
    }

    /**
     * @return array{0: ?string, 1: ?Model}
     */
    protected function resolveRequestedParty(Request $request): array
    {
        if ($request->filled('client_id')) {
            return ['client', Client::query()->findOrFail($request->client_id)];
        }
        if ($request->filled('person_id')) {
            return ['person', Person::query()->findOrFail($request->person_id)];
        }
        if ($request->filled('vendor_id')) {
            return ['vendor', Vendor::query()->findOrFail($request->vendor_id)];
        }

        return [null, null];
    }

    protected function findParty(?string $type, mixed $id): ?Model
    {
        return match ($type) {
            'client' => Client::query()->find($id),
            'person' => Person::query()->find($id),
            'vendor' => Vendor::query()->find($id),
            default => null,
        };
    }

    /**
     * @return array<string, \Illuminate\Support\Collection>
     */
    protected function partyOptions(?Model $party = null): array
    {
        $clients = tenantBusinessEnabled()
            ? Client::query()->active()->orderBy('name')->get(['id', 'name', 'contact_name', 'company_name'])
            : collect();
        $persons = Person::query()->active()->orderBy('name')->get(['id', 'name']);
        $vendors = tenantBusinessEnabled()
            ? Vendor::query()->active()->orderBy('name')->get(['id', 'name', 'type'])
            : collect();

        if ($party instanceof Client && $clients->doesntContain('id', $party->id)) {
            $clients = $clients->prepend($party);
        } elseif ($party instanceof Person && $persons->doesntContain('id', $party->id)) {
            $persons = $persons->prepend($party);
        } elseif ($party instanceof Vendor && $vendors->doesntContain('id', $party->id)) {
            $vendors = $vendors->prepend($party);
        }

        return compact('clients', 'persons', 'vendors');
    }

    protected function redirectTo(PartyBalanceMemo $memo): string
    {
        $party = $memo->relationLoaded('party') ? $memo->party : $memo->party()->first();

        if ($party instanceof Client) {
            return route('cp.clients.show', $party);
        }
        if ($party instanceof Person) {
            return route('cp.persons.show', $party);
        }
        if ($party instanceof Vendor) {
            return route('cp.'.$party->type->routePrefix().'.show', $party);
        }

        return route('cp.dashboard');
    }
}
