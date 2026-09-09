<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Services\Export\PdfExporter;
use App\Services\Finance\ClientStatementService;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = DateRange::fromRequest($request);

        $clients = Client::query()
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
                if (Schema::hasColumn('clients', 'contact_name')) {
                    $qq->orWhere('contact_name', 'like', "%{$term}%");
                }
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->when($from || $to, function ($q) use ($from, $to) {
                $q->where(function ($qq) use ($from, $to) {
                    $qq->whereHas('services', fn ($s) => DateRange::constrain($s, 'service_date', $from, $to))
                        ->orWhereHas('cashPayments', fn ($p) => DateRange::constrain($p->active(), 'occurred_on', $from, $to));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $currencies = Currency::query()->active()->get();
        $periodQuery = DateRange::queryParams($from, $to);

        return view('cp.finance.clients.index', compact('clients', 'currencies', 'from', 'to', 'periodQuery'));
    }

    public function create()
    {
        return view('cp.finance.clients.form', ['client' => new Client(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $client = Client::query()->create($data);

        return redirect()->route('cp.clients.show', $client)->with('success', 'تم إضافة الزبون.');
    }

    public function show(Request $request, Client $client, ClientStatementService $statements)
    {
        return view('cp.finance.clients.show', $this->showPayload($request, $client, $statements));
    }

    public function exportPdf(Request $request, Client $client, PdfExporter $pdf, ClientStatementService $statements)
    {
        $request->validate([
            'opening' => ['sometimes', 'array'],
            'opening.*' => ['nullable', 'numeric'],
        ]);

        $data = $this->showPayload($request, $client, $statements);
        $data['exporting'] = true;

        $suffix = ($data['from'] || $data['to'])
            ? '-'.($data['from'] ?: 'start').'-'.($data['to'] ?: 'now')
            : '';

        return $pdf->download(
            'cp.finance.clients.print',
            $data,
            'client-'.$client->id.$suffix.'.pdf'
        );
    }

    public function edit(Client $client)
    {
        return view('cp.finance.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->validated($request));

        return redirect()->route('cp.clients.show', $client)->with('success', 'تم تحديث بيانات الزبون.');
    }

    public function destroy(Client $client)
    {
        if ($client->hasFinancialHistory()) {
            $client->update(['is_active' => false]);

            return redirect()->route('cp.clients.index')
                ->with('success', 'تم أرشفة الزبون لأنه يملك سجلاً مالياً (لا يمكن حذفه نهائياً).');
        }

        $client->forceDelete();

        return redirect()->route('cp.clients.index')->with('success', 'تم حذف الزبون.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => Phone::rules(),
            'notes' => ['nullable', 'string'],
        ], ['phone.regex' => Phone::message()]) + ['is_active' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected function showPayload(Request $request, Client $client, ClientStatementService $statements): array
    {
        [$from, $to] = DateRange::fromRequest($request);
        $opening = $request->input('opening', []);

        return $statements->build($client, $from, $to, is_array($opening) ? $opening : []);
    }

    public function unpaidServices(Client $client, Request $request)
    {
        $ils = Currency::byCode('ILS');
        $currencyId = (int) ($request->currency_id ?: $ils->id);
        $currency = Currency::query()->find($currencyId);
        $outstanding = $client->outstandingAmount($currencyId);

        $services = ClientService::query()
            ->billable()
            ->where('client_id', $client->id)
            ->when($currencyId, fn ($q) => $q->where('currency_id', $currencyId))
            ->with(['currency', 'fxCurrency'])
            ->orderByDesc('service_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'amount' => $s->amount,
                'currency_id' => $s->currency_id,
                'currency_code' => $s->currency->code,
                'is_fx' => $s->isFx(),
                'source_amount' => $s->source_amount,
            ]);

        return response()->json([
            'outstanding' => $outstanding,
            'outstanding_formatted' => $currency?->format($outstanding) ?? $outstanding,
            'is_credit' => Money::isNegative($outstanding),
            'credit' => Money::isNegative($outstanding) ? Money::abs($outstanding) : '0.00',
            'credit_formatted' => Money::isNegative($outstanding)
                ? ($currency?->format(Money::abs($outstanding)) ?? Money::abs($outstanding))
                : null,
            'services' => $services,
        ]);
    }
}
