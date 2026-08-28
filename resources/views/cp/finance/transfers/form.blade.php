@extends('cp.layout')
@section('title', 'تحويل / صرف عملة')
@section('content')
<div x-data="transferForm()" class="max-w-2xl">
<form method="post" action="{{ route('cp.transfers.store') }}" class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    <p class="text-sm text-slate-500">
        التحويل داخل نفس العملة ينقل بين طريقتي دفع.
        عند اختلاف العملة: أدخل سعر الصرف يدوياً — <strong>المبلغ بعد التحويل = المبلغ قبل التحويل ÷ سعر الصرف</strong>
        (مثال: 365 شيكل ÷ 3.65 = 100 دولار).
    </p>
    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="text-sm">الصندوق *</label>
            <select name="fund_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($funds as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">التاريخ *</label>
            <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-sm">من طريقة دفع *</label>
            <select name="from_payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">إلى طريقة دفع *</label>
            <select name="to_payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
            </select>
            <p class="text-xs text-slate-500 mt-1" x-show="!isFx">يجب أن تختلف عن المصدر عند نفس العملة.</p>
            <p class="text-xs text-slate-500 mt-1" x-show="isFx">يمكن أن تكون نفس الطريقة عند صرف العملة.</p>
        </div>
        <div>
            <label class="text-sm">العملة المصدر *</label>
            <select name="currency_id" x-model="fromCurrencyId" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">العملة الوجهة *</label>
            <select name="to_currency_id" x-model="toCurrencyId" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">المبلغ قبل التحويل *</label>
            <input type="number" step="0.01" min="0.01" name="amount" x-model="amount" @input="recalcFromAmount()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-sm">رسوم (بنفس عملة المصدر)</label>
            <input type="number" step="0.01" min="0" name="fee_amount" value="0" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <template x-if="isFx">
            <div class="md:col-span-2 grid md:grid-cols-2 gap-3 rounded-xl border border-dashed p-4 bg-slate-50 dark:bg-slate-900/40">
                <div>
                    <label class="text-sm">سعر التحويل (يدوي) *</label>
                    <input type="number" step="0.00000001" min="0.00000001" name="exchange_rate" x-model="rate" @input="recalcFromRate()" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" placeholder="مثال: 3.65">
                    <p class="text-xs text-slate-500 mt-1">كم من عملة المصدر مقابل 1 من الوجهة (شيكل→دولار: 3.65)</p>
                </div>
                <div>
                    <label class="text-sm">المبلغ بعد التحويل *</label>
                    <input type="number" step="0.01" min="0.01" name="to_amount" x-model="toAmount" @input="recalcFromToAmount()" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                </div>
            </div>
        </template>
    </div>
    <div>
        <label class="text-sm">ملاحظة</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></textarea>
    </div>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">تنفيذ التحويل</button>
</form>
</div>
@endsection
@push('scripts')
<script>
function transferForm() {
    const first = @json((string) ($currencies->first()?->id ?? ''));
    return {
        fromCurrencyId: first,
        toCurrencyId: first,
        amount: '',
        rate: '',
        toAmount: '',
        get isFx() {
            return String(this.fromCurrencyId) !== String(this.toCurrencyId);
        },
        recalcFromRate() {
            const a = parseFloat(this.amount) || 0;
            const r = parseFloat(this.rate) || 0;
            if (a > 0 && r > 0) this.toAmount = (a / r).toFixed(2);
        },
        recalcFromAmount() {
            if (this.isFx && this.rate) this.recalcFromRate();
        },
        recalcFromToAmount() {
            const a = parseFloat(this.amount) || 0;
            const t = parseFloat(this.toAmount) || 0;
            if (a > 0 && t > 0) this.rate = (a / t).toFixed(8);
        }
    }
}
</script>
@endpush
