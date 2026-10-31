<?php

namespace App\Http\Controllers\Cp;

use App\Enums\ClientServiceStatus;
use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\ServiceType;
use App\Services\Finance\ClientWorkService;
use App\Support\DateRange;
use App\Support\TableSort;
use Illuminate\Http\Request;

class ClientServiceController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $services = TableSort::paginate(
            ClientService::query()
                ->with(['client', 'currency', 'fxCurrency', 'serviceType'])
                ->when(! \App\Support\ArchiveVisibility::showing(), function ($q) {
                    $q->whereHas('client', fn ($client) => $client->where('is_active', true));
                })
                ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
                ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
                ->tap(fn ($q) => DateRange::constrain($q, 'service_date', $from, $to))
                ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                    $qq->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                })),
            [
                'client' => ['query' => fn () => Client::query()->select('name')->whereColumn('clients.id', 'client_services.client_id')],
                'type' => ['query' => fn () => ServiceType::query()->select('name')->whereColumn('service_types.id', 'client_services.service_type_id')],
                'details' => ['column' => 'title'],
                'amount' => ['column' => 'amount', 'dir' => 'desc'],
                'date' => ['column' => 'service_date', 'dir' => 'desc'],
            ],
            'date'
        );

        return view('cp.finance.client-services.index', [
            'services' => $services,
            'clients' => Client::query()->active()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    public function create(Request $request)
    {
        return view('cp.finance.client-services.form', [
            'service' => new ClientService([
                'client_id' => $request->client_id,
                'service_date' => now()->toDateString(),
                'status' => ClientServiceStatus::Completed,
            ]),
            'clients' => Client::query()->active()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    public function store(Request $request, ClientWorkService $service)
    {
        $data = $this->validated($request);
        try {
            $row = $service->create($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $row->client_id)->with('success', 'تم تسجيل الخدمة.');
    }

    public function edit(ClientService $clientService)
    {
        $clientService->load(['currency', 'fxCurrency', 'serviceType']);

        return view('cp.finance.client-services.form', [
            'service' => $clientService,
            'clients' => $this->clientChoices($clientService->client),
        ] + $this->financeLookups());
    }

    public function update(Request $request, ClientService $clientService, ClientWorkService $service)
    {
        // A free-title service («أخرى…») keeps its title when edited without picking a type.
        if (! $request->filled('service_type_id') && ! $request->filled('title')) {
            $request->merge(['title' => $clientService->title]);
        }

        try {
            $service->update($clientService, $this->validated($request));
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $clientService->client_id)->with('success', 'تم تحديث الخدمة.');
    }

    public function destroy(ClientService $clientService, ClientWorkService $service)
    {
        try {
            $service->delete($clientService);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $clientService->client_id)->with('success', 'انحذفت الخدمة.');
    }

    protected function validated(Request $request): array
    {
        $currency = Currency::query()->find($request->input('currency_id'));
        $isFx = $currency && $currency->code !== 'ILS';

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'service_type_id' => ['nullable', 'required_without:title', 'exists:service_types,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'service_date' => DateRange::inputRules(),
            'notes' => ['nullable', 'string'],
        ]);

        $type = filled($data['service_type_id'] ?? null) ? ServiceType::query()->find($data['service_type_id']) : null;
        $data['service_type_id'] = $type?->id;
        $data['title'] = $type?->name ?: (trim((string) ($data['title'] ?? '')) ?: 'خدمة');
        $data['status'] = ClientServiceStatus::Completed->value;

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

    protected function clientChoices(?Client $current = null)
    {
        $clients = Client::query()->active()->orderBy('name')->get();
        if ($current && ! $clients->contains('id', $current->id)) {
            $clients->prepend($current);
        }

        return $clients;
    }
}
