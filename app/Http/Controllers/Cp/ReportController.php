<?php

namespace App\Http\Controllers\Cp;

use App\Enums\LoanDirection;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Expense;
use App\Models\FamilyLoan;
use App\Models\FamilyMember;
use App\Services\Export\PdfExporter;
use App\Services\Finance\BalanceService;
use App\Services\Finance\ProfitService;
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
        $iOwe = $balances->familyBalance(LoanDirection::Borrowed);
        $theyOwe = $balances->familyBalance(LoanDirection::Lent);

        $from = $request->from;
        $to = $request->to;

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

        $familySummary = FamilyMember::query()->orderBy('name')->get()->map(function (FamilyMember $member) use ($snapshot) {
            $rows = [];
            foreach ($snapshot['currencies'] as $currency) {
                $owe = $member->iOweAmount($currency->id);
                $owed = $member->theyOweAmount($currency->id);
                if (\App\Support\Money::isZero($owe) && \App\Support\Money::isZero($owed)) {
                    continue;
                }
                $rows[] = compact('currency', 'owe', 'owed');
            }

            return ['member' => $member, 'rows' => $rows];
        })->filter(fn ($r) => $r['rows'] !== []);

        $revenue = tenantBusinessEnabled()
            ? ClientPayment::query()
                ->active()
                ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
                ->with(['client', 'currency', 'paymentMethod'])
                ->orderByDesc('payment_date')
                ->limit(100)
                ->get()
            : collect();

        $expenses = Expense::query()
            ->active()
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->with(['fund', 'category', 'currency', 'paymentMethod', 'vendor'])
            ->orderByDesc('expense_date')
            ->limit(100)
            ->get();

        $openLoans = FamilyLoan::query()
            ->active()
            ->whereIn('status', ['open', 'partial'])
            ->with(['familyMember', 'currency'])
            ->orderBy('loan_date')
            ->get();

        return [
            'snapshot' => $snapshot,
            'receivables' => tenantBusinessEnabled() ? $receivables : [],
            'iOwe' => $iOwe,
            'theyOwe' => $theyOwe,
            'clientSummary' => $clientSummary,
            'familySummary' => $familySummary,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'openLoans' => $openLoans,
            'profitRows' => $profit->forPeriod($from, $to),
            'from' => $from,
            'to' => $to,
            'exportedAt' => now()->format('Y-m-d H:i'),
            'title' => 'التقارير',
        ] + $this->financeLookups();
    }
}
