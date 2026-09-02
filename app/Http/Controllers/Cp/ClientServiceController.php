<?php

namespace App\Http\Controllers\Cp;

use App\Enums\ClientServiceStatus;
use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Services\Finance\ClientWorkService;
use App\Support\DateRange;
use Illuminate\Http\Request;

class ClientServiceController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $services = ClientService::query()
            ->with(['client', 'currency', 'fxCurrency', 'serviceType'])
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->tap(fn ($q) => DateRange::constrain($q, 'service_date', $from, $to))
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('title', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            }))
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.client-services.index', [
            'services' => $services,
            'clients' => Client::query()->orderBy('name')->get(),
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
        $clientService->load(['currency', 'fxCurrency']);

        return view('cp.finance.client-services.form', [
            'service' => $clientService,
            'clients' => Client::query()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    public function update(Request $request, ClientService $clientService, ClientWorkService $service)
    {
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

        return redirect()->route('cp.client-services.index')->with('success', 'تم حذف الخدمة.');
    }

    protected function validated(Request $request): array
    {
        $isFx = $request->boolean('requires_fx');
        $ils = Currency::byCode('ILS');
        $usd = Currency::byCode('USD');

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'service_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['currency_id'] = $ils->id;
        $data['status'] = ClientServiceStatus::Completed->value;
        $data['description'] = null;

        if ($isFx) {
            $data['fx_currency_id'] = $usd->id;
        } else {
            $data['source_amount'] = null;
            $data['exchange_rate'] = null;
            $data['fx_currency_id'] = null;
        }

        return $data;
    }
}
