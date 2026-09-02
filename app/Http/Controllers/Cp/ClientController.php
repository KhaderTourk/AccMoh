<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Currency;
use App\Services\Export\PdfExporter;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('contact_name', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
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
        return view('cp.finance.clients.show', $this->showPayload($client));
    }

    public function exportPdf(Client $client, PdfExporter $pdf)
    {
        $data = $this->showPayload($client);
        $data['exporting'] = true;

        return $pdf->download(
            'cp.finance.clients.print',
            $data,
            'client-'.$client->id.'.pdf'
        );
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

    /**
     * @return array{client: Client, currencies: \Illuminate\Support\Collection, timeline: \Illuminate\Support\Collection, exportedAt: string}
     */
    protected function showPayload(Client $client): array
    {
        $client->load([
            'services' => fn ($q) => $q->with(['currency', 'fxCurrency', 'serviceType'])
                ->orderBy('service_date')
                ->orderBy('id'),
            'payments' => fn ($q) => $q->with(['currency', 'fxCurrency', 'paymentMethod'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id'),
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
                'notes' => $service->notes,
            ]);
        }
        foreach ($client->payments->where('is_reversed', false) as $payment) {
            $timeline->push([
                'date' => $payment->payment_date,
                'type' => 'payment',
                'title' => 'دفعة عبر '.$payment->paymentMethod->name,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'notes' => $payment->notes,
            ]);
        }

        $timeline = $timeline->sortByDesc(fn ($i) => $i['date']->format('Y-m-d'))->values();

        return [
            'client' => $client,
            'currencies' => $currencies,
            'serviceGroups' => $this->groupServices($client->services),
            'paymentGroups' => $this->groupPayments($client->payments),
            'timeline' => $timeline,
            'exportedAt' => now()->format('Y-m-d H:i'),
            'title' => $client->name,
            'subtitle' => trim(implode(' · ', array_filter([$client->contact_name, $client->phone]))),
        ];
    }

    protected function groupServices(Collection $services): Collection
    {
        return $services
            ->groupBy(fn ($s) => $s->service_type_id ?: 0)
            ->map(function (Collection $rows) {
                $type = $rows->first()->serviceType;
                $rows = $rows->sortBy([
                    fn ($s) => $s->service_date->format('Y-m-d'),
                    fn ($s) => $s->id,
                ])->values();

                return [
                    'name' => $type?->name ?: 'بدون نوع',
                    'uncategorized' => $type === null,
                    'services' => $rows,
                    'totals' => $this->totalsByCurrency($rows),
                ];
            })
            ->sortBy([
                fn ($group) => $group['uncategorized'] ? 1 : 0,
                fn ($group) => $group['name'],
            ])
            ->values();
    }

    protected function groupPayments(Collection $payments): Collection
    {
        return $payments
            ->groupBy('payment_method_id')
            ->map(function (Collection $rows) {
                $method = $rows->first()->paymentMethod;
                $rows = $rows->sortByDesc(fn ($p) => $p->payment_date->format('Y-m-d').sprintf('%010d', $p->id))->values();

                return [
                    'name' => $method?->name ?: '—',
                    'sort' => $method?->sort_order ?? 999,
                    'payments' => $rows,
                    'totals' => $this->totalsByCurrency($rows->where('is_reversed', false)),
                ];
            })
            ->sortBy('sort')
            ->values();
    }

    protected function totalsByCurrency(Collection $rows): Collection
    {
        return $rows
            ->groupBy('currency_id')
            ->map(function (Collection $byCurrency) {
                $currency = $byCurrency->first()->currency;
                $total = $byCurrency->reduce(fn ($sum, $row) => Money::add($sum, $row->amount), '0');

                return [
                    'currency' => $currency,
                    'total' => $total,
                    'formatted' => $currency->format($total),
                ];
            })
            ->values();
    }
}
