<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientService;
use App\Models\Currency;
use App\Services\Finance\ClientPaymentService;
use App\Services\Finance\ReversalService;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $payments = ClientPayment::query()
            ->with(['client', 'currency', 'fxCurrency', 'paymentMethod'])
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->payment_method_id, fn ($q, $id) => $q->where('payment_method_id', $id))
            ->tap(fn ($q) => DateRange::constrain($q, 'payment_date', $from, $to))
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('payer_name', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            }))
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.payments.index', [
            'payments' => $payments,
            'clients' => Client::query()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    public function create(Request $request)
    {
        return view('cp.finance.payments.form', [
            'clients' => Client::query()->active()->orderBy('name')->get(),
            'selectedClientId' => $request->client_id,
        ] + $this->financeLookups());
    }

    public function store(Request $request, ClientPaymentService $service)
    {
        $isFx = $request->boolean('requires_fx');
        $ils = Currency::byCode('ILS');
        $usd = Currency::byCode('USD');

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['currency_id'] = $ils->id;
        if ($isFx) {
            $data['fx_currency_id'] = $usd->id;
        } else {
            $data['source_amount'] = null;
            $data['exchange_rate'] = null;
            $data['fx_currency_id'] = null;
        }

        try {
            $payment = $service->receive($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $payment->client_id)->with('success', 'تم تسجيل الدفعة.');
    }

    public function show(ClientPayment $payment)
    {
        $payment->load(['client', 'currency', 'fxCurrency', 'paymentMethod']);

        return view('cp.finance.payments.show', compact('payment'));
    }

    public function reverse(ClientPayment $payment, ReversalService $reversals)
    {
        try {
            $reversals->reversePayment($payment);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم إلغاء الدفعة وإرجاع أثرها المالي.');
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
