<?php

namespace App\Http\Controllers\Cp;

use App\Enums\GoodsTakeCategory;
use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\ClientGoodsTake;
use App\Models\Currency;
use App\Models\Person;
use App\Models\Vendor;
use App\Services\Finance\ClientGoodsTakeService;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientGoodsTakeController extends Controller
{
    use LoadsFinanceLookups;

    public function create(Request $request)
    {
        [$partyType, $party] = $this->resolveRequestedParty($request);

        return view('cp.finance.client-goods-takes.form', [
            'take' => new ClientGoodsTake([
                'client_id' => $party instanceof Client ? $party->id : null,
                'party_type' => $partyType,
                'party_id' => $party?->id,
                'taken_on' => now()->toDateString(),
            ]),
            'parties' => $this->partyOptions($party),
            'selectedPartyType' => $partyType,
            'selectedPartyId' => $party?->id,
        ] + $this->financeLookups());
    }

    public function store(Request $request, ClientGoodsTakeService $service)
    {
        $data = $this->validated($request);

        try {
            $row = $service->create($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($this->redirectTo($row))->with('success', 'تم تسجيل المنتج المأخوذ وخصمه من الحساب.');
    }

    public function edit(ClientGoodsTake $clientGoodsTake)
    {
        $clientGoodsTake->load(['party', 'client', 'currency', 'fxCurrency']);

        return view('cp.finance.client-goods-takes.form', [
            'take' => $clientGoodsTake,
            'parties' => $this->partyOptions($clientGoodsTake->party),
            'selectedPartyType' => $clientGoodsTake->party_type ?: 'client',
            'selectedPartyId' => $clientGoodsTake->party_id ?: $clientGoodsTake->client_id,
        ] + $this->financeLookups());
    }

    public function update(Request $request, ClientGoodsTake $clientGoodsTake, ClientGoodsTakeService $service)
    {
        try {
            $updated = $service->update($clientGoodsTake, $this->validated($request, $clientGoodsTake));
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($this->redirectTo($updated))->with('success', 'تم تحديث المنتج المأخوذ.');
    }

    public function destroy(ClientGoodsTake $clientGoodsTake, ClientGoodsTakeService $service)
    {
        $redirect = $this->redirectTo($clientGoodsTake);

        try {
            $service->delete($clientGoodsTake);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect($redirect)->with('success', 'تم حذف المنتج المأخوذ.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?ClientGoodsTake $take = null): array
    {
        $currency = Currency::query()->find($request->input('currency_id'));
        $isFx = $currency && $currency->code !== 'ILS';
        $this->mergeParty($request);

        $data = $request->validate([
            'party_key' => ['required', 'string'],
            'party_type' => ['required', Rule::in(['client', 'person', 'vendor'])],
            'party_id' => ['required', 'integer'],
            'category' => ['required', Rule::enum(GoodsTakeCategory::class)],
            'title' => ['nullable', 'required_if:category,other', 'string', 'max:255'],
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'taken_on' => DateRange::inputRules(),
            'notes' => ['nullable', 'string'],
        ], [
            'party_key.required' => 'اختر جهة من الزبائن أو الأشخاص أو الموظفين أو الموردين.',
        ]);

        if ($take) {
            $data['party_type'] = $take->party_type ?: 'client';
            $data['party_id'] = $take->party_id ?: $take->client_id;
        }

        $party = $this->findParty($data['party_type'], $data['party_id']);
        if (! $party) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'party_key' => 'اختر جهة من الزبائن أو الأشخاص أو الموظفين أو الموردين.',
            ]);
        }

        $data['client_id'] = $data['party_type'] === 'client' ? $data['party_id'] : null;
        unset($data['party_key']);

        $category = GoodsTakeCategory::from($data['category']);
        $data['title'] = $category === GoodsTakeCategory::Other
            ? trim((string) ($data['title'] ?? ''))
            : $category->label();
        unset($data['category']);
        if ($data['title'] === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'title' => 'اكتب عنوان التصنيف.',
            ]);
        }

        if ($isFx && empty($data['source_amount']) && filled($data['amount'] ?? null)) {
            $data['source_amount'] = $data['amount'];
        }
        $hydrated = \App\Support\PaymentFx::hydrate($data);
        $data['currency_id'] = $hydrated['currency_id'];
        $data['fx_currency_id'] = $hydrated['fx_currency_id'] ?? null;
        $data['source_amount'] = $hydrated['source_amount'] ?? null;
        $data['exchange_rate'] = $hydrated['exchange_rate'] ?? null;

        return $data;
    }

    protected function mergeParty(Request $request): void
    {
        if ($request->filled('party_key') && preg_match('/^(client|person|vendor):(\d+)$/', (string) $request->party_key, $match)) {
            $request->merge([
                'party_type' => $match[1],
                'party_id' => (int) $match[2],
            ]);

            return;
        }

        if ($request->filled('client_id') && ! $request->filled('party_type')) {
            $request->merge([
                'party_key' => 'client:'.$request->client_id,
                'party_type' => 'client',
                'party_id' => (int) $request->client_id,
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

    protected function redirectTo(ClientGoodsTake $row): string
    {
        $party = $row->relationLoaded('party') ? $row->party : $row->party()->first();

        if ($party instanceof Client) {
            return route('cp.clients.show', $party);
        }
        if ($party instanceof Person) {
            return route('cp.persons.show', $party);
        }
        if ($party instanceof Vendor) {
            return route('cp.'.$party->type->routePrefix().'.show', $party);
        }
        if ($row->client_id) {
            return route('cp.clients.show', $row->client_id);
        }

        return route('cp.clients.index');
    }
}
