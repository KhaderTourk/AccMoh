@extends('cp.layout')
@section('title', $payment->exists ? 'تعديل '.$direction->label() : $direction->label())
@section('content')
@php
    $ilsId = (string) ($ilsCurrency?->id ?? '');
    $action = $payment->exists
        ? route('cp.payments.update', $payment)
        : route('cp.payments.store', $direction->value);
    $workers = ($parties['vendors'] ?? collect())->filter(fn ($v) => $v->type === \App\Enums\VendorType::Worker);
    $suppliers = ($parties['vendors'] ?? collect())->filter(fn ($v) => $v->type === \App\Enums\VendorType::Supplier);
    $initialPartyKey = old('party_key', ($selectedPartyType && $selectedPartyId) ? $selectedPartyType.':'.$selectedPartyId : '');
    $initialPartyName = old('name', $payment->name);
@endphp
<div x-data="cashPaymentForm()" class="max-w-3xl space-y-4">
<form method="post" action="{{ $action }}" class="rounded-2xl bg-white dark:bg-slate-800 border p-6 space-y-4">
    @csrf
    @if($payment->exists) @method('PUT') @endif
    <input type="hidden" name="party_type" value="{{ $selectedPartyType }}" x-model="partyType">
    <input type="hidden" name="party_id" value="{{ $selectedPartyId }}" x-model="partyId">
    <input type="hidden" name="name" value="{{ $initialPartyName }}" x-model="partyName">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="text-sm">تاريخ الدفعة *</label>
            <input type="date" name="occurred_on" value="{{ old('occurred_on', optional($payment->occurred_on)->format('Y-m-d') ?? date('Y-m-d')) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-sm">الاسم *</label>
            <select name="party_key" x-ref="partySelect" x-model="partyKey" @change="syncParty()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" @disabled($partyLocked)>
                <option value="">اختر الاسم</option>
                @if(($parties['clients'] ?? collect())->isNotEmpty())
                    <optgroup label="الزبائن">
                        @foreach($parties['clients'] as $c)
                            <option value="client:{{ $c->id }}" data-name="{{ $c->name }}" @selected($initialPartyKey === 'client:'.$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if(($parties['persons'] ?? collect())->isNotEmpty())
                    <optgroup label="الأشخاص">
                        @foreach($parties['persons'] as $p)
                            <option value="person:{{ $p->id }}" data-name="{{ $p->name }}" @selected($initialPartyKey === 'person:'.$p->id)>{{ $p->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($workers->isNotEmpty())
                    <optgroup label="الموظفون">
                        @foreach($workers as $w)
                            <option value="vendor:{{ $w->id }}" data-name="{{ $w->name }}" @selected($initialPartyKey === 'vendor:'.$w->id)>{{ $w->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($suppliers->isNotEmpty())
                    <optgroup label="الموردون">
                        @foreach($suppliers as $s)
                            <option value="vendor:{{ $s->id }}" data-name="{{ $s->name }}" @selected($initialPartyKey === 'vendor:'.$s->id)>{{ $s->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            @if($partyLocked)
                <input type="hidden" name="party_key" value="{{ $initialPartyKey }}">
            @endif
        </div>
        <div>
            <label class="text-sm">الدرج *</label>
            <select name="fund_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($funds as $fund)
                    <option value="{{ $fund->id }}" @selected(old('fund_id', $payment->fund_id)==$fund->id)>{{ $fund->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">طريقة الدفع *</label>
            <select name="payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($paymentMethods as $m)
                    <option value="{{ $m->id }}" @selected(old('payment_method_id', $payment->payment_method_id)==$m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">العملة *</label>
            <select name="currency_id" x-model="currencyId" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($currencies as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">اسم صاحب الحساب</label>
            <input name="account_holder_name" value="{{ old('account_holder_name', $payment->account_holder_name) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
    </div>

    <div x-show="!isFx">
        <label class="text-sm">المبلغ *</label>
        <input type="number" step="0.01" min="0.01" name="amount" x-model="amount" x-bind:disabled="isFx" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>
    <div x-show="isFx" x-cloak class="space-y-3 rounded-xl border border-dashed p-4">
        <div>
            <label class="text-sm">المبلغ *</label>
            <input type="number" step="0.01" min="0.01" name="source_amount" x-model="amount" x-bind:disabled="!isFx" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-sm">سعر الصرف *</label>
            <input type="number" step="0.0001" min="0.0001" name="exchange_rate" x-model="rate" x-bind:disabled="!isFx" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" placeholder="مثال: 3.65">
        </div>
        <div>
            <label class="text-sm">المجموع بالشيكل</label>
            <input type="text" :value="ilsTotal()" readonly tabindex="-1" class="w-full rounded-xl border px-3 py-2 bg-slate-50 dark:bg-slate-700/60">
            <input type="hidden" name="amount" :value="ilsTotal()" x-bind:disabled="!isFx">
        </div>
    </div>

    <div>
        <label class="text-sm">ملاحظات</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $payment->notes) }}</textarea>
    </div>

    <div class="flex gap-2">
        <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
        <a href="{{ $direction === \App\Enums\PaymentDirection::Incoming ? route('cp.payments.incoming') : route('cp.payments.outgoing') }}" class="px-5 py-2 rounded-xl border">إلغاء</a>
    </div>
</form>
</div>
@endsection
@push('scripts')
<script>
function cashPaymentForm() {
    return {
        ilsId: @json($ilsId),
        currencyId: @json((string) old('currency_id', $payment->fx_currency_id ?: $payment->currency_id ?: $ilsId)),
        amount: @json((string) old('source_amount', old('amount', $payment->source_amount ?: $payment->amount))),
        rate: @json((string) old('exchange_rate', $payment->formattedExchangeRate())),
        partyKey: @json($initialPartyKey),
        partyType: @json($selectedPartyType ?: ''),
        partyId: @json($selectedPartyId ? (string) $selectedPartyId : ''),
        partyName: @json($initialPartyName ?: ''),
        init() { this.syncParty(); },
        syncParty() {
            const key = String(this.partyKey || '');
            const [type, id] = key.includes(':') ? key.split(':') : ['', ''];
            this.partyType = type || '';
            this.partyId = id || '';
            const select = this.$refs.partySelect;
            const option = select?.selectedOptions?.[0];
            this.partyName = option?.dataset?.name || this.partyName || '';
        },
        get isFx() { return String(this.currencyId) !== String(this.ilsId); },
        ilsTotal() {
            const amt = parseFloat(this.amount) || 0;
            const rate = parseFloat(this.rate) || 0;
            return (Math.round(amt * rate * 100) / 100).toFixed(2);
        }
    }
}
</script>
@endpush
