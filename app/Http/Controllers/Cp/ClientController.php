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
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $currencies = Currency::query()->active()->get();

        // Every client (archived included) in one list; filtering and search happen in the browser.
        $clients = Client::query()
            ->orderByRaw("COALESCE(NULLIF(company_name, ''), name)")
            ->get()
            ->map(function (Client $client) use ($currencies) {
                $balances = [];
                foreach ($currencies as $currency) {
                    $due = $client->outstandingAmount($currency->id);
                    if (! Money::isZero($due)) {
                        $balances[] = [
                            'code' => $currency->code,
                            'amount' => (float) $due,
                            'label' => $currency->format(Money::abs($due)),
                        ];
                    }
                }

                return [
                    'id' => $client->id,
                    'org' => (string) $client->organization(),
                    'name' => $client->personName(),
                    'phone' => (string) $client->phone,
                    'notes' => (string) $client->notes,
                    'archived' => ! $client->is_active,
                    'balances' => $balances,
                    'show' => route('cp.clients.show', $client),
                    'pdf' => route('cp.clients.export-pdf', $client),
                    'update' => route('cp.clients.update', $client),
                    'archive' => route('cp.clients.destroy', $client),
                    'restore' => route('cp.clients.restore', $client),
                ];
            })
            ->values();

        $currencyFormats = $currencies->mapWithKeys(fn (Currency $c) => [$c->code => ['symbol' => $c->symbol]])->all();
        $flash = (int) $request->query('flash');

        return view('cp.finance.clients.index', compact('clients', 'currencyFormats', 'flash'));
    }

    public function create()
    {
        return view('cp.finance.clients.form', ['client' => new Client(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = true;
        $client = Client::query()->create($data);

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.clients.index', ['flash' => $client->id])->with('success', 'انضاف الزبون.');
        }

        return redirect()->route('cp.clients.show', $client)->with('success', 'انضاف الزبون.');
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

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.clients.index', ['flash' => $client->id])->with('success', 'انحفظ التعديل.');
        }

        return redirect()->route('cp.clients.show', $client)->with('success', 'انحفظ التعديل.');
    }

    public function destroy(Client $client)
    {
        $client->update(['is_active' => false]);

        return redirect()->route('cp.clients.index')
            ->with('success', 'انأرشف الزبون، وبتلاقيه مع كل حركاته بتاب «مؤرشف».');
    }

    public function restore(Client $client)
    {
        $client->update(['is_active' => true]);

        return redirect()->route('cp.clients.index', ['flash' => $client->id])
            ->with('success', 'رجع الزبون لقائمة الزبائن.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => Phone::rules(),
            'notes' => ['nullable', 'string'],
        ], ['phone.regex' => Phone::message()]);
        // The control panel form no longer sends is_active (archiving has its own action); the API still may.
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
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
