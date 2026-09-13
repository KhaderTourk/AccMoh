<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\ClientGoodsTake;
use App\Models\Currency;
use App\Services\Finance\ClientGoodsTakeService;
use Illuminate\Http\Request;

class ClientGoodsTakeController extends Controller
{
    use LoadsFinanceLookups;

    public function create(Request $request)
    {
        $client = $this->resolveClient($request->client_id);

        return view('cp.finance.client-goods-takes.form', [
            'take' => new ClientGoodsTake([
                'client_id' => $client->id,
                'taken_on' => now()->toDateString(),
            ]),
            'client' => $client,
            'clients' => Client::query()->active()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    public function store(Request $request, ClientGoodsTakeService $service)
    {
        $data = $this->validated($request);
        $this->resolveClient($data['client_id']);

        try {
            $row = $service->create($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $row->client_id)->with('success', 'تم تسجيل المنتج المأخوذ وخصمه من حساب الزبون.');
    }

    public function edit(ClientGoodsTake $clientGoodsTake)
    {
        $clientGoodsTake->load(['client', 'currency', 'fxCurrency']);

        return view('cp.finance.client-goods-takes.form', [
            'take' => $clientGoodsTake,
            'client' => $clientGoodsTake->client,
            'clients' => Client::query()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    public function update(Request $request, ClientGoodsTake $clientGoodsTake, ClientGoodsTakeService $service)
    {
        try {
            $service->update($clientGoodsTake, $this->validated($request, $clientGoodsTake));
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $clientGoodsTake->client_id)->with('success', 'تم تحديث المنتج المأخوذ.');
    }

    public function destroy(ClientGoodsTake $clientGoodsTake, ClientGoodsTakeService $service)
    {
        $clientId = $clientGoodsTake->client_id;

        try {
            $service->delete($clientGoodsTake);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $clientId)->with('success', 'تم حذف المنتج المأخوذ.');
    }

    protected function resolveClient(mixed $id): Client
    {
        abort_unless(filled($id), 404);

        return Client::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?ClientGoodsTake $take = null): array
    {
        $currency = Currency::query()->find($request->input('currency_id'));
        $isFx = $currency && $currency->code !== 'ILS';

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'taken_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($take) {
            $data['client_id'] = $take->client_id;
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
}
