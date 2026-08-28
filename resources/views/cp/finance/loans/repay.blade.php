@extends('cp.layout')
@section('title', 'تسوية دائن أو مدين')
@section('content')
<div x-data="repayForm()" x-init="init()" class="max-w-3xl">
<form method="post" action="{{ route('cp.family-loans.repay.store') }}" class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4" @submit="prepareSubmit">
    @csrf
    <input type="hidden" name="currency_id" :value="currencyId">
    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="text-sm">الفرد *</label>
            <select name="family_member_id" x-model="memberId" @change="loadLoans()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">اختر</option>
                @foreach($members as $m)<option value="{{ $m->id }}" @selected(old('family_member_id', $selectedMemberId)==$m->id)>{{ $m->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">النوع *</label>
            <select name="direction" x-model="direction" @change="loadLoans()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($directions as $d)<option value="{{ $d->value }}" @selected(old('direction', $selectedDirection)==$d->value)>{{ $d->label() }}</option>@endforeach
            </select>
            <p class="text-xs text-slate-500 mt-1">دائن = أرجّع له · مدين = يسترجع لي</p>
        </div>
        <div>
            <label class="text-sm">طريقة الدفع *</label>
            <select name="payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
            </select>
            <p class="text-xs text-slate-500 mt-1">يمكن التسوية بأي طريقة دفع حتى لو اختلفت عن الحركة الأصلية.</p>
        </div>
        <div>
            <label class="text-sm">التاريخ *</label>
            <input type="date" name="repayment_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
    </div>
    @include('cp.partials.ils-fx-fields')
    <div><label class="text-sm">ملاحظة</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></textarea></div>
    <div class="rounded-xl border border-dashed p-4">
        <div class="flex justify-between mb-3"><h3 class="font-bold">توزيع على الحركات</h3><button type="button" @click="autoFill()" class="text-sm text-primary">توزيع تلقائي</button></div>
        <template x-for="(l, idx) in loans" :key="l.id">
            <div class="grid grid-cols-12 gap-2 mb-2 text-sm items-center">
                <div class="col-span-7">
                    <input type="hidden" :name="`allocations[${idx}][family_loan_id]`" :value="l.id">
                    <span x-text="l.loan_date + ' — متبقي ' + l.remaining"></span>
                </div>
                <div class="col-span-5"><input type="number" step="0.01" min="0" :name="`allocations[${idx}][amount]`" x-model="l.allocate" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
            </div>
        </template>
        <p class="text-sm">المجموع: <strong x-text="allocatedSum().toFixed(2)"></strong></p>
    </div>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ التسوية</button>
</form>
</div>
@endsection
@push('scripts')
<script>
function repayForm() {
    return {
        memberId: '{{ old('family_member_id', $selectedMemberId) }}',
        direction: '{{ old('direction', $selectedDirection) }}',
        currencyId: '{{ $ilsCurrency?->id }}',
        requiresFx: @json(filter_var(old('requires_fx', false), FILTER_VALIDATE_BOOLEAN)),
        usdAmount: '{{ old('source_amount') }}',
        rate: '{{ old('exchange_rate') }}',
        ilsAmount: '{{ old('amount') }}',
        loans: [],
        ilsTotal() {
            const usd = parseFloat(this.usdAmount) || 0;
            const r = parseFloat(this.rate) || 0;
            return (Math.round(usd * r * 100) / 100).toFixed(2);
        },
        settlementAmount() {
            return this.requiresFx ? parseFloat(this.ilsTotal()) || 0 : parseFloat(this.ilsAmount) || 0;
        },
        init() { if (this.memberId) this.loadLoans(); },
        async loadLoans() {
            this.loans = [];
            if (!this.memberId) return;
            const url = @json(url('/cp/family-members')) + '/' + this.memberId + '/open-loans?currency_id=' + this.currencyId + '&direction=' + this.direction;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            this.loans = (data.loans || []).map(l => ({ ...l, allocate: 0 }));
        },
        allocatedSum() { return this.loans.reduce((t, l) => t + (parseFloat(l.allocate)||0), 0); },
        autoFill() {
            let left = this.settlementAmount();
            this.loans.forEach(l => {
                const rem = parseFloat(l.remaining)||0;
                const take = Math.min(left, rem);
                l.allocate = take.toFixed(2);
                left = Math.round((left-take)*100)/100;
            });
        },
        prepareSubmit(e) {
            if (this.allocatedSum() < 0.001 && this.settlementAmount() > 0) {
                this.autoFill();
            }
            if (Math.abs(this.allocatedSum() - this.settlementAmount()) > 0.001) {
                e.preventDefault(); alert('مجموع التوزيع يجب أن يساوي المبلغ.');
            }
        }
    }
}
</script>
@endpush
