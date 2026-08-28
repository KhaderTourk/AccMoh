@extends('cp.layout')
@section('title', 'تسجيل دفعة عميل')
@section('content')
<div x-data="paymentForm()" x-init="init()" class="max-w-3xl space-y-4">
<form method="post" action="{{ route('cp.payments.store') }}" class="rounded-2xl bg-white dark:bg-slate-800 border p-6 space-y-4">
    @csrf
    <input type="hidden" name="currency_id" value="{{ $ilsCurrency?->id }}">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="text-sm">العميل *</label>
            <select name="client_id" x-model="clientId" @change="loadDue()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">اختر</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected(old('client_id', $selectedClientId)==$c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">طريقة الدفع *</label>
            <select name="payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">المرسل</label>
            <input name="payer_name" value="{{ old('payer_name') }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-sm">التاريخ *</label>
            <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="hidden" name="requires_fx" value="0">
        <input type="checkbox" name="requires_fx" value="1" x-model="requiresFx" class="rounded border">
        سعر الصرف / دولار
    </label>

    <div x-show="!requiresFx">
        <label class="text-sm">المبلغ / شيكل *</label>
        <input type="number" step="0.01" min="0.01" name="amount" x-model="ilsAmount" x-bind:disabled="requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>

    <div x-show="requiresFx" x-cloak class="space-y-3 rounded-xl border border-dashed p-4">
        <div>
            <label class="text-sm">المبلغ / دولار *</label>
            <input type="number" step="0.01" min="0.01" name="source_amount" x-model="usdAmount" x-bind:disabled="!requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-sm">سعر الدولار *</label>
            <input type="number" step="0.0001" min="0.0001" name="exchange_rate" x-model="rate" x-bind:disabled="!requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" placeholder="مثال: 3.65">
        </div>
        <div>
            <label class="text-sm">الإجمالي / شيكل</label>
            <input type="text" :value="ilsTotal()" readonly tabindex="-1" class="w-full rounded-xl border px-3 py-2 bg-slate-50 dark:bg-slate-700/60">
            <input type="hidden" name="amount" :value="ilsTotal()" x-bind:disabled="!requiresFx">
        </div>
        <p class="text-xs text-slate-500">تُسجَّل الحركة بالشيكل. الإجمالي = المبلغ بالدولار × سعر الدولار.</p>
    </div>

    <div>
        <label class="text-sm">ملاحظة</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes') }}</textarea>
    </div>

    <div class="rounded-xl border border-dashed p-4 space-y-2">
        <p class="font-bold text-sm">المستحق على العميل (شيكل)</p>
        <p class="text-xs text-slate-500" x-show="loading">جاري التحميل...</p>
        <p class="text-lg font-extrabold text-amber-600 dark:text-amber-300" x-show="!loading && !isCredit && Number(due) > 0" x-text="dueFormatted"></p>
        <p class="text-lg font-extrabold text-emerald-600 dark:text-emerald-300" x-show="!loading && isCredit" x-text="'عربون / رصيد مدفوع مقدماً: ' + creditFormatted"></p>
        <p class="text-sm text-slate-500" x-show="!loading && clientId && !isCredit && Number(due) <= 0">لا يوجد مستحق حالياً — يمكن تسجيل عربون قبل تقديم الخدمة.</p>
        <template x-if="!loading && services.length">
            <ul class="text-xs text-slate-500 dark:text-slate-400 space-y-1 pt-2">
                <template x-for="s in services" :key="s.id">
                    <li x-text="s.title + ' — ' + s.amount"></li>
                </template>
            </ul>
        </template>
        <p class="text-xs text-slate-500">تُخصم الدفعة من إجمالي المستحق على العميل، وليس من خدمة بعينها. يمكن الدفع حتى بدون خدمات.</p>
    </div>

    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ الدفعة</button>
</form>
</div>
@endsection
@push('scripts')
<script>
function paymentForm() {
    return {
        clientId: '{{ old('client_id', $selectedClientId) }}',
        ilsId: '{{ $ilsCurrency?->id }}',
        requiresFx: @json(filter_var(old('requires_fx', false), FILTER_VALIDATE_BOOLEAN)),
        usdAmount: '{{ old('source_amount') }}',
        rate: '{{ old('exchange_rate') }}',
        ilsAmount: '{{ old('amount') }}',
        due: '0',
        dueFormatted: '',
        isCredit: false,
        creditFormatted: '',
        services: [],
        loading: false,
        ilsTotal() {
            const usd = parseFloat(this.usdAmount) || 0;
            const rate = parseFloat(this.rate) || 0;
            return (Math.round(usd * rate * 100) / 100).toFixed(2);
        },
        init() { if (this.clientId) this.loadDue(); },
        async loadDue() {
            this.services = [];
            this.due = '0';
            this.dueFormatted = '';
            this.isCredit = false;
            this.creditFormatted = '';
            if (!this.clientId || !this.ilsId) return;
            this.loading = true;
            const url = @json(url('/cp/clients')) + '/' + this.clientId + '/unpaid-services?currency_id=' + this.ilsId;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            this.due = data.outstanding || '0';
            this.dueFormatted = data.outstanding_formatted || this.due;
            this.isCredit = !!data.is_credit;
            this.creditFormatted = data.credit_formatted || data.credit || '';
            this.services = data.services || [];
            this.loading = false;
        }
    }
}
</script>
@endpush
