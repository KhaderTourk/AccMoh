@extends('cp.layout')
@section('title', 'تسجيل دفعة عميل')
@section('content')
<div x-data="paymentForm()" x-init="init()" class="max-w-3xl space-y-4">
<form method="post" action="{{ route('cp.payments.store') }}" class="rounded-2xl bg-white dark:bg-slate-800 border p-6 space-y-4" @submit="prepareSubmit">
    @csrf
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
            <label class="text-sm">العملة *</label>
            <select name="currency_id" x-model="currencyId" @change="loadDue()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($currencies as $c)<option value="{{ $c->id }}" @selected(old('currency_id')==$c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">المبلغ *</label>
            <input type="number" step="0.01" min="0.01" name="amount" x-model="amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
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
    <div>
        <label class="text-sm">ملاحظة</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes') }}</textarea>
    </div>

    <div class="rounded-xl border border-dashed p-4 space-y-2">
        <p class="font-bold text-sm">المستحق على العميل</p>
        <p class="text-xs text-slate-500" x-show="loading">جاري التحميل...</p>
        <p class="text-lg font-extrabold text-amber-600 dark:text-amber-300" x-show="!loading && dueFormatted" x-text="dueFormatted"></p>
        <p class="text-sm text-amber-600 dark:text-amber-300" x-show="!loading && clientId && Number(due) <= 0">لا يوجد مبلغ مستحق على هذا العميل بهذه العملة.</p>
        <template x-if="!loading && services.length">
            <ul class="text-xs text-slate-500 dark:text-slate-400 space-y-1 pt-2">
                <template x-for="s in services" :key="s.id">
                    <li x-text="s.title + ' — ' + s.amount"></li>
                </template>
            </ul>
        </template>
        <p class="text-xs text-slate-500">تُخصم الدفعة من إجمالي المستحق على العميل، وليس من خدمة بعينها.</p>
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
        currencyId: '{{ old('currency_id', $currencies->first()?->id) }}',
        amount: '{{ old('amount') }}',
        due: '0',
        dueFormatted: '',
        services: [],
        loading: false,
        init() { if (this.clientId) this.loadDue(); },
        async loadDue() {
            this.services = [];
            this.due = '0';
            this.dueFormatted = '';
            if (!this.clientId || !this.currencyId) return;
            this.loading = true;
            const url = @json(url('/cp/clients')) + '/' + this.clientId + '/unpaid-services?currency_id=' + this.currencyId;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            this.due = data.outstanding || '0';
            this.dueFormatted = data.outstanding_formatted || this.due;
            this.services = data.services || [];
            this.loading = false;
        },
        prepareSubmit(e) {
            const pay = parseFloat(this.amount) || 0;
            const due = parseFloat(this.due) || 0;
            if (due <= 0) {
                e.preventDefault();
                alert('لا يوجد مبلغ مستحق على العميل بهذه العملة.');
                return;
            }
            if (pay - due > 0.001) {
                e.preventDefault();
                alert('مبلغ الدفعة أكبر من المستحق على العميل.');
            }
        }
    }
}
</script>
@endpush
