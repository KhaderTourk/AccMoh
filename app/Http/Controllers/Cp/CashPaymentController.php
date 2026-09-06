<?php

namespace App\Http\Controllers\Cp;

use App\Enums\PaymentDirection;
use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\Person;
use App\Models\Vendor;
use App\Services\Finance\CashPaymentService;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashPaymentController extends Controller
{
    use LoadsFinanceLookups;

    public function incoming(Request $request)
    {
        return $this->index($request, PaymentDirection::Incoming);
    }

    public function outgoing(Request $request)
    {
        return $this->index($request, PaymentDirection::Outgoing);
    }

    public function create(Request $request, string $direction)
    {
        $dir = PaymentDirection::from($direction);
        [$partyType, $party] = $this->resolveRequestedParty($request);

        $fundId = $request->fund_id;
        if (! $fundId) {
            $fundId = $party instanceof Person
                ? Fund::family()->id
                : (tenantBusinessEnabled() ? Fund::business()->id : Fund::family()->id);
        }

        return view('cp.finance.payments.form', [
            'payment' => new CashPayment([
                'direction' => $dir,
                'occurred_on' => now()->toDateString(),
                'fund_id' => $fundId,
                'name' => $party?->name,
                'party_type' => $partyType,
                'party_id' => $party?->id,
            ]),
            'direction' => $dir,
            'partyLocked' => $party !== null,
            'selectedPartyType' => $partyType,
            'selectedPartyId' => $party?->id,
            'parties' => $this->partyOptions(),
        ] + $this->financeLookups());
    }

    public function store(Request $request, string $direction, CashPaymentService $service)
    {
        $dir = PaymentDirection::from($direction);
        try {
            $payment = $service->record($this->validated($request, $dir));
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($this->afterSave($payment))->with('success', 'تم تسجيل الدفعة.');
    }

    public function show(CashPayment $payment)
    {
        $payment->load(['party', 'fund', 'currency', 'fxCurrency', 'paymentMethod']);

        return view('cp.finance.payments.show', compact('payment'));
    }

    public function edit(CashPayment $payment)
    {
        abort_if($payment->is_reversed, 404);

        return view('cp.finance.payments.form', [
            'payment' => $payment,
            'direction' => $payment->direction,
            'partyLocked' => filled($payment->party_id),
            'selectedPartyType' => $this->partyKey($payment),
            'selectedPartyId' => $payment->party_id,
            'parties' => $this->partyOptions(),
        ] + $this->financeLookups());
    }

    public function update(Request $request, CashPayment $payment, CashPaymentService $service)
    {
        try {
            $updated = $service->update($payment, $this->validated($request, $payment->direction, $payment));
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($this->afterSave($updated))->with('success', 'تم تعديل الدفعة.');
    }

    public function destroy(CashPayment $payment, CashPaymentService $service)
    {
        try {
            $service->delete($payment);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        $route = $payment->isIncoming() ? 'cp.payments.incoming' : 'cp.payments.outgoing';

        return redirect()->route($route)->with('success', 'تم حذف الدفعة وإلغاء أثرها المالي.');
    }

    protected function index(Request $request, PaymentDirection $direction)
    {
        [$from, $to] = $this->dateRange($request);

        $payments = CashPayment::query()
            ->with(['party', 'fund', 'currency', 'fxCurrency', 'paymentMethod'])
            ->where('direction', $direction)
            ->when($request->fund_id, fn ($q, $id) => $q->where('fund_id', $id))
            ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->payment_method_id, fn ($q, $id) => $q->where('payment_method_id', $id))
            ->tap(fn ($q) => DateRange::constrain($q, 'occurred_on', $from, $to))
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('account_holder_name', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            }))
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cp.finance.payments.index', [
            'payments' => $payments,
            'direction' => $direction,
        ] + $this->financeLookups());
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, PaymentDirection $direction, ?CashPayment $payment = null): array
    {
        $currency = Currency::query()->find($request->input('currency_id'));
        $isFx = $currency && $currency->code !== 'ILS';

        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'fund_id' => ['required', 'exists:funds,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'party_type' => ['nullable', Rule::in(['client', 'person', 'vendor'])],
            'party_id' => ['nullable', 'integer'],
        ]);

        $data['direction'] = $direction;
        if ($isFx && empty($data['source_amount']) && filled($data['amount'] ?? null)) {
            $data['source_amount'] = $data['amount'];
        }

        return $data;
    }

    /**
     * @return array{0: ?string, 1: \Illuminate\Database\Eloquent\Model|null}
     */
    protected function resolveRequestedParty(Request $request): array
    {
        if ($request->filled('client_id')) {
            return ['client', Client::query()->find($request->client_id)];
        }
        if ($request->filled('person_id')) {
            return ['person', Person::query()->find($request->person_id)];
        }
        if ($request->filled('vendor_id')) {
            return ['vendor', Vendor::query()->find($request->vendor_id)];
        }
        if ($request->filled('party_type') && $request->filled('party_id')) {
            $party = match ($request->party_type) {
                'client' => Client::query()->find($request->party_id),
                'person' => Person::query()->find($request->party_id),
                'vendor' => Vendor::query()->find($request->party_id),
                default => null,
            };

            return [$request->party_type, $party];
        }

        return [null, null];
    }

    /**
     * @return array<string, \Illuminate\Support\Collection>
     */
    protected function partyOptions(): array
    {
        return [
            'clients' => tenantBusinessEnabled() ? Client::query()->active()->orderBy('name')->get(['id', 'name']) : collect(),
            'persons' => Person::query()->active()->orderBy('name')->get(['id', 'name']),
            'vendors' => tenantBusinessEnabled() ? Vendor::query()->active()->orderBy('name')->get(['id', 'name', 'type']) : collect(),
        ];
    }

    protected function partyKey(CashPayment $payment): ?string
    {
        return match ($payment->party_type) {
            'client', Client::class => 'client',
            'person', Person::class => 'person',
            'vendor', Vendor::class => 'vendor',
            default => $payment->party_type,
        };
    }

    protected function afterSave(CashPayment $payment): string
    {
        $party = $payment->party;
        if ($party instanceof Client) {
            return route('cp.clients.show', $party);
        }
        if ($party instanceof Person) {
            return route('cp.persons.show', $party);
        }
        if ($party instanceof Vendor) {
            return route('cp.'.$party->type->routePrefix().'.show', $party);
        }

        return route($payment->isIncoming() ? 'cp.payments.incoming' : 'cp.payments.outgoing');
    }
}
