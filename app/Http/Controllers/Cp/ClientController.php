<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Currency;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('contact_name', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $currencies = Currency::query()->active()->get();

        return view('cp.finance.clients.index', compact('clients', 'currencies'));
    }

    public function create()
    {
        return view('cp.finance.clients.form', ['client' => new Client(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $client = Client::query()->create($data);

        return redirect()->route('cp.clients.show', $client)->with('success', 'تم إضافة العميل.');
    }

    public function show(Client $client)
    {
        $client->load([
            'services.currency',
            'services.fxCurrency',
            'services.serviceType',
            'payments' => fn ($q) => $q->with(['currency', 'fxCurrency', 'paymentMethod'])->latest('payment_date'),
        ]);
        $currencies = Currency::query()->active()->get();

        $timeline = collect();
        foreach ($client->services as $service) {
            $timeline->push([
                'date' => $service->service_date,
                'type' => 'service',
                'title' => 'خدمة: '.$service->title,
                'amount' => $service->amount,
                'currency' => $service->currency,
            ]);
        }
        foreach ($client->payments->where('is_reversed', false) as $payment) {
            $timeline->push([
                'date' => $payment->payment_date,
                'type' => 'payment',
                'title' => 'دفعة عبر '.$payment->paymentMethod->name,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);
        }

        $timeline = $timeline->sortByDesc(fn ($i) => $i['date']->format('Y-m-d'))->values();

        return view('cp.finance.clients.show', compact('client', 'currencies', 'timeline'));
    }

    public function edit(Client $client)
    {
        return view('cp.finance.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->validated($request));

        return redirect()->route('cp.clients.show', $client)->with('success', 'تم تحديث بيانات العميل.');
    }

    public function destroy(Client $client)
    {
        if ($client->hasFinancialHistory()) {
            $client->update(['is_active' => false]);

            return redirect()->route('cp.clients.index')
                ->with('success', 'تم أرشفة العميل لأنه يملك سجلاً مالياً (لا يمكن حذفه نهائياً).');
        }

        $client->forceDelete();

        return redirect()->route('cp.clients.index')->with('success', 'تم حذف العميل.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
