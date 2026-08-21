<?php

namespace App\Http\Controllers\Cp;

use App\Enums\ClientServiceStatus;
use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\ClientService;
use App\Services\Finance\ClientWorkService;
use Illuminate\Http\Request;

class ClientServiceController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        $services = ClientService::query()
            ->with(['client', 'currency', 'serviceType'])
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('service_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('service_date', '<=', $d))
            ->when($request->q, fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.client-services.index', [
            'services' => $services,
            'clients' => Client::query()->orderBy('name')->get(),
            'statuses' => ClientServiceStatus::cases(),
        ] + $this->financeLookups());
    }

    public function create(Request $request)
    {
        return view('cp.finance.client-services.form', [
            'service' => new ClientService([
                'client_id' => $request->client_id,
                'service_date' => now()->toDateString(),
                'status' => ClientServiceStatus::Pending,
            ]),
            'clients' => Client::query()->active()->orderBy('name')->get(),
            'statuses' => ClientServiceStatus::cases(),
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
        return view('cp.finance.client-services.form', [
            'service' => $clientService,
            'clients' => Client::query()->orderBy('name')->get(),
            'statuses' => ClientServiceStatus::cases(),
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
            $clientId = $clientService->client_id;
            $service->delete($clientService);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $clientId)->with('success', 'تم حذف الخدمة.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'service_date' => ['required', 'date'],
            'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
