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
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    use LoadsFinanceLookups;

    public function index(Request $request)
    {
        $payments = ClientPayment::query()
            ->with(['client', 'currency', 'paymentMethod'])
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->payment_method_id, fn ($q, $id) => $q->where('payment_method_id', $id))
            ->when($request->from, fn ($q, $d) => $q->whereDate('payment_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('payment_date', '<=', $d))
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
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $payment = $service->receive($data);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cp.clients.show', $payment->client_id)->with('success', 'تم تسجيل الدفعة.');
    }

    public function show(ClientPayment $payment)
    {
        $payment->load(['client', 'currency', 'paymentMethod']);

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
        $currencyId = (int) $request->currency_id;
        $currency = $currencyId ? Currency::query()->find($currencyId) : null;
        $outstanding = $currencyId ? $client->outstandingAmount($currencyId) : '0.00';

        $services = ClientService::query()
            ->billable()
            ->where('client_id', $client->id)
            ->when($currencyId, fn ($q) => $q->where('currency_id', $currencyId))
            ->with('currency')
            ->orderByDesc('service_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'amount' => $s->amount,
                'currency_id' => $s->currency_id,
                'currency_code' => $s->currency->code,
            ]);

        return response()->json([
            'outstanding' => $outstanding,
            'outstanding_formatted' => $currency?->format($outstanding) ?? $outstanding,
            'services' => $services,
        ]);
    }
}
