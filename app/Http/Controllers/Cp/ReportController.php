<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Person;
use App\Services\Export\PdfExporter;
use App\Services\Finance\BalanceService;
use App\Services\Finance\ProfitService;
use App\Support\DateRange;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request, BalanceService $balances, ProfitService $profit)
    {
        return view('cp.finance.reports.index', $this->payload($request, $balances, $profit));
    }

    public function exportPdf(Request $request, BalanceService $balances, ProfitService $profit, PdfExporter $pdf)
    {
        return $pdf->download(
            'cp.finance.reports.print',
            $this->payload($request, $balances, $profit),
            'reports-'.now()->format('Y-m-d').'.pdf'
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request, BalanceService $balances, ProfitService $profit): array
    {
        $snapshot = $balances->snapshot();
        $receivables = $balances->clientReceivables();
        $personNet = $balances->personNet();

        [$from, $to] = $this->dateRange($request);

        $clientSummary = tenantBusinessEnabled()
            ? Client::query()->orderBy('name')->get()->map(function (Client $client) use ($snapshot) {
                $rows = [];
                foreach ($snapshot['currencies'] as $currency) {
                    $billed = $client->billedAmount($currency->id);
                    $paid = $client->paidAmount($currency->id);
                    $due = $client->outstandingAmount($currency->id);
                    if (\App\Support\Money::isZero($billed) && \App\Support\Money::isZero($paid)) {
                        continue;
                    }
                    $rows[] = compact('currency', 'billed', 'paid', 'due');
                }

                return ['client' => $client, 'rows' => $rows];
            })->filter(fn ($r) => $r['rows'] !== [])
            : collect();

        $personSummary = Person::query()->orderBy('name')->get()->map(function (Person $person) use ($snapshot) {
            $rows = [];
            foreach ($snapshot['currencies'] as $currency) {
                $in = $person->incomingAmount($currency->id);
                $out = $person->outgoingAmount($currency->id);
                if (\App\Support\Money::isZero($in) && \App\Support\Money::isZero($out)) {
                    continue;
                }
                $rows[] = compact('currency', 'in', 'out');
            }

            return ['member' => $person, 'rows' => $rows];
        })->filter(fn ($r) => $r['rows'] !== []);

        $incoming = CashPayment::query()
            ->incoming()
            ->active()
            ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to))
            ->with(['party', 'currency', 'paymentMethod'])
            ->orderByDesc('occurred_on')
            ->limit(100)
            ->get();

        $outgoing = CashPayment::query()
            ->outgoing()
            ->active()
            ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to))
            ->with(['party', 'fund', 'currency', 'paymentMethod'])
            ->orderByDesc('occurred_on')
            ->limit(100)
            ->get();

        return [
            'snapshot' => $snapshot,
            'receivables' => tenantBusinessEnabled() ? $receivables : [],
            'personNet' => $personNet,
            'clientSummary' => $clientSummary,
            'personSummary' => $personSummary,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'profitRows' => $profit->forPeriod($from, $to),
            'from' => $from,
            'to' => $to,
            'periodLabel' => DateRange::label($from, $to),
            'exportedAt' => now()->format('Y-m-d H:i'),
            'title' => 'التقارير',
        ] + $this->financeLookups();
    }
}
