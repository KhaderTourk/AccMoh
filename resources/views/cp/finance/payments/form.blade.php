@extends('cp.layout')
@section('title', 'تسجيل دفعة عميل')
@section('content')
<div x-data="paymentForm()" x-init="init()" class="max-w-3xl space-y-4">
<form method="post" action="{{ route('cp.payments.store') }}" class="rounded-2xl bg-white dark:bg-slate-800 border p-6 space-y-4" @submit="prepareSubmit">
    @csrf
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="text-sm">العميل *</label>
            <select name="client_id" x-model="clientId" @change="loadServices()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">اختر</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected(old('client_id', $selectedClientId)==$c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">العملة *</label>
            <select name="currency_id" x-model="currencyId" @change="loadServices()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
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

    <div class="rounded-xl border border-dashed p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-bold">توزيع الدفعة على الخدمات</h3>
            <button type="button" @click="autoFill()" class="text-sm text-primary">توزيع تلقائي</button>
        </div>
        <p class="text-xs text-slate-500 mb-3" x-show="loading">جاري التحميل...</p>
        <template x-if="!loading && services.length === 0">
            <p class="text-sm text-amber-600">لا توجد خدمات غير مسددة لهذه العملة. سجّل خدمة أولاً.</p>
        </template>
        <template x-for="(s, idx) in services" :key="s.id">
            <div class="grid grid-cols-12 gap-2 items-center mb-2 text-sm">
                <div class="col-span-6">
                    <input type="hidden" :name="`allocations[${idx}][client_service_id]`" :value="s.id">
                    <span x-text="s.title"></span>
                    <span class="text-xs text-slate-500" x-text="'متبقي: ' + s.remaining"></span>
                </div>
                <div class="col-span-6">
                    <input type="number" step="0.01" min="0" :name="`allocations[${idx}][amount]`" x-model="s.allocate" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                </div>
            </div>
        </template>
        <p class="text-sm mt-2">مجموع التوزيع: <strong x-text="allocatedSum().toFixed(2)"></strong> / <span x-text="parseFloat(amount||0).toFixed(2)"></span></p>
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
        services: [],
        loading: false,
        init() { if (this.clientId) this.loadServices(); },
        async loadServices() {
            this.services = [];
            if (!this.clientId || !this.currencyId) return;
            this.loading = true;
            const url = @json(url('/cp/clients')) + '/' + this.clientId + '/unpaid-services?currency_id=' + this.currencyId;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            this.services = (data.services || []).map(s => ({ ...s, allocate: 0 }));
            this.loading = false;
        },
        allocatedSum() {
            return this.services.reduce((t, s) => t + (parseFloat(s.allocate) || 0), 0);
        },
        autoFill() {
            let left = parseFloat(this.amount) || 0;
            this.services.forEach(s => {
                const rem = parseFloat(s.remaining) || 0;
                const take = Math.min(left, rem);
                s.allocate = take.toFixed(2);
                left = Math.round((left - take) * 100) / 100;
            });
        },
        prepareSubmit(e) {
            if (Math.abs(this.allocatedSum() - (parseFloat(this.amount)||0)) > 0.001) {
                e.preventDefault();
                alert('مجموع التوزيع يجب أن يساوي مبلغ الدفعة.');
            }
        }
    }
}
</script>
@endpush
