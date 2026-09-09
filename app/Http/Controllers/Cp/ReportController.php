<?php

namespace App\Http\Controllers\Cp;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Person;
use App\Models\Vendor;
use App\Services\Export\PdfExporter;
use App\Services\Finance\BalanceService;
use App\Services\Finance\ProfitService;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request, BalanceService $balances, ProfitService $profit)
    {
        return view('cp.finance.reports.index', $this->payload($request, $balances, $profit, false));
    }

    public function exportPdf(Request $request, BalanceService $balances, ProfitService $profit, PdfExporter $pdf)
    {
        [$from, $to] = $this->dateRange($request);
        $name = 'reports-'.($from ?: 'all').'-'.($to ?: now()->toDateString()).'.pdf';

        return $pdf->download(
            'cp.finance.reports.print',
            $this->payload($request, $balances, $profit, true),
            $name
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request, BalanceService $balances, ProfitService $profit, bool $forExport): array
    {
        $snapshot = $balances->snapshot();
        $receivables = $balances->clientReceivables();
        $personNet = $balances->personNet();
        [$from, $to] = $this->dateRange($request);

        $currencyId = $request->filled('currency_id') ? (int) $request->currency_id : null;
        $clientId = $request->filled('client_id') ? (int) $request->client_id : null;
        $personId = $request->filled('person_id') ? (int) $request->person_id : null;
        $partyType = (string) $request->input('party_type', '');
        $hasOpening = filled($from);
        $currencies = $snapshot['currencies'];
        $filteredCurrencies = $currencyId
            ? $currencies->where('id', $currencyId)->values()
            : $currencies;

        $showClients = tenantBusinessEnabled() && ! in_array($partyType, ['person', 'worker', 'supplier'], true);
        $showPersons = ! in_array($partyType, ['client', 'worker', 'supplier'], true);
        $showWorkers = tenantBusinessEnabled() && ! in_array($partyType, ['client', 'person', 'supplier'], true);
        $showSuppliers = tenantBusinessEnabled() && ! in_array($partyType, ['client', 'person', 'worker'], true);

        $clientSummary = $showClients
            ? Client::query()
                ->when($clientId, fn ($q) => $q->where('id', $clientId))
                ->orderBy('name')
                ->get()
                ->map(function (Client $client) use ($filteredCurrencies, $from, $to, $hasOpening) {
                    $rows = [];
                    foreach ($filteredCurrencies as $currency) {
                        $opening = $hasOpening ? $client->openingBalance($currency->id, $from) : '0.00';
                        $billed = $client->billedAmount($currency->id, $from, $to);
                        $paid = $client->paidAmount($currency->id, $from, $to);
                        $due = Money::sub(Money::add($opening, $billed), $paid);
                        if (Money::isZero($opening) && Money::isZero($billed) && Money::isZero($paid)) {
                            continue;
                        }
                        $rows[] = compact('currency', 'opening', 'billed', 'paid', 'due');
                    }

                    return ['client' => $client, 'rows' => $rows];
                })->filter(fn ($r) => $r['rows'] !== [])->values()
            : collect();

        $personSummary = $showPersons
            ? Person::query()
                ->when($personId, fn ($q) => $q->where('id', $personId))
                ->orderBy('name')
                ->get()
                ->map(function (Person $person) use ($filteredCurrencies, $from, $to) {
                    $rows = [];
                    foreach ($filteredCurrencies as $currency) {
                        $in = $person->incomingAmount($currency->id, $from, $to);
                        $out = $person->outgoingAmount($currency->id, $from, $to);
                        if (Money::isZero($in) && Money::isZero($out)) {
                            continue;
                        }
                        $rows[] = compact('currency', 'in', 'out');
                    }

                    return ['member' => $person, 'rows' => $rows];
                })->filter(fn ($r) => $r['rows'] !== [])->values()
            : collect();

        $workerSummary = $showWorkers ? $balances->vendorAccountSummary(VendorType::Worker, $from, $to) : collect();
        $supplierSummary = $showSuppliers ? $balances->vendorAccountSummary(VendorType::Supplier, $from, $to) : collect();
        if ($currencyId) {
            $workerSummary = $this->filterVendorSummaryCurrency($workerSummary, $currencyId);
            $supplierSummary = $this->filterVendorSummaryCurrency($supplierSummary, $currencyId);
        }

        $profitRows = ($showClients || $partyType === '') ? $profit->forPeriod($from, $to) : [];
        if ($currencyId) {
            $profitRows = array_values(array_filter(
                $profitRows,
                fn ($row) => (int) $row['currency']->id === $currencyId
            ));
        }

        $incomingQuery = $this->filteredPayments($request, 'incoming', $from, $to);
        $outgoingQuery = $this->filteredPayments($request, 'outgoing', $from, $to);
        $incomingCount = (clone $incomingQuery)->count();
        $outgoingCount = (clone $outgoingQuery)->count();
        $incomingLimit = $forExport ? null : 200;
        $incoming = $incomingQuery->with(['party', 'currency', 'paymentMethod', 'fund'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->when($incomingLimit, fn ($q) => $q->limit($incomingLimit))
            ->get();
        $outgoing = $outgoingQuery->with(['party', 'fund', 'currency', 'paymentMethod'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->when($incomingLimit, fn ($q) => $q->limit($incomingLimit))
            ->get();

        $filterQuery = DateRange::queryParams($from, $to, array_filter([
            'currency_id' => $request->currency_id,
            'fund_id' => $request->fund_id,
            'payment_method_id' => $request->payment_method_id,
            'party_type' => $request->party_type,
            'client_id' => $request->client_id,
            'person_id' => $request->person_id,
            'q' => $request->q,
        ], fn ($v) => filled($v)));

        return [
            'snapshot' => $snapshot,
            'receivables' => tenantBusinessEnabled() ? $receivables : [],
            'personNet' => $personNet,
            'clientSummary' => $clientSummary,
            'personSummary' => $personSummary,
            'workerSummary' => $workerSummary,
            'supplierSummary' => $supplierSummary,
            'workerPayables' => tenantBusinessEnabled() ? $balances->vendorPayables(VendorType::Worker) : [],
            'supplierPayables' => tenantBusinessEnabled() ? $balances->vendorPayables(VendorType::Supplier) : [],
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'incomingCount' => $incomingCount,
            'outgoingCount' => $outgoingCount,
            'incomingTotals' => $this->totalsByCurrency($incoming),
            'outgoingTotals' => $this->totalsByCurrency($outgoing),
            'profitRows' => $profitRows,
            'from' => $from,
            'to' => $to,
            'hasOpening' => $hasOpening,
            'periodLabel' => DateRange::label($from, $to),
            'filterQuery' => $filterQuery,
            'clients' => tenantBusinessEnabled() ? Client::query()->orderBy('name')->get(
                Schema::hasColumn('clients', 'contact_name')
                    ? ['id', 'name', 'contact_name', 'company_name']
                    : ['id', 'name', 'company_name']
            ) : collect(),
            'persons' => Person::query()->orderBy('name')->get(['id', 'name']),
            'exportedAt' => now()->format('Y-m-d H:i'),
            'title' => 'التقارير',
            'forExport' => $forExport,
        ] + $this->financeLookups();
    }

    protected function filteredPayments(Request $request, string $direction, ?string $from, ?string $to): Builder
    {
        $query = CashPayment::query()
            ->{$direction}()
            ->active()
            ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to))
            ->when($request->filled('currency_id'), fn ($q) => $q->where('currency_id', $request->currency_id))
            ->when($request->filled('fund_id'), fn ($q) => $q->where('fund_id', $request->fund_id))
            ->when($request->filled('payment_method_id'), fn ($q) => $q->where('payment_method_id', $request->payment_method_id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->q;
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                });
            });

        $partyType = (string) $request->input('party_type', '');
        if ($partyType === 'client' || $request->filled('client_id')) {
            $query->where('party_type', 'client');
            if ($request->filled('client_id')) {
                $query->where('party_id', $request->client_id);
            }
        } elseif ($partyType === 'person' || $request->filled('person_id')) {
            $query->where('party_type', 'person');
            if ($request->filled('person_id')) {
                $query->where('party_id', $request->person_id);
            }
        } elseif ($partyType === 'worker') {
            $ids = Vendor::query()->ofType(VendorType::Worker)->pluck('id');
            $query->where('party_type', 'vendor')->whereIn('party_id', $ids->isEmpty() ? [0] : $ids);
        } elseif ($partyType === 'supplier') {
            $ids = Vendor::query()->ofType(VendorType::Supplier)->pluck('id');
            $query->where('party_type', 'vendor')->whereIn('party_id', $ids->isEmpty() ? [0] : $ids);
        }

        return $query;
    }

    protected function totalsByCurrency(Collection $payments): Collection
    {
        return $payments
            ->groupBy('currency_id')
            ->map(function (Collection $rows) {
                $currency = $rows->first()->currency;
                $total = $rows->reduce(fn ($sum, $row) => Money::add($sum, $row->amount), '0');

                return [
                    'currency' => $currency,
                    'total' => $total,
                    'formatted' => $currency->format($total),
                ];
            })
            ->values();
    }

    protected function filterVendorSummaryCurrency(Collection $summary, int $currencyId): Collection
    {
        return $summary
            ->map(function (array $row) use ($currencyId) {
                $row['rows'] = array_values(array_filter(
                    $row['rows'],
                    fn ($r) => (int) $r['currency']->id === $currencyId
                ));

                return $row;
            })
            ->filter(fn ($row) => $row['rows'] !== [])
            ->values();
    }
}
