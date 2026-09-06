@extends('cp.layout')
@section('title', $vendor->type->chargeFormTitle($charge->exists))
@section('content')
@php
    $ilsId = (string) ($ilsCurrency?->id ?? '');
    $backUrl = route('cp.'.$vendor->type->routePrefix().'.show', $vendor);
@endphp
<form method="post"
      action="{{ $charge->exists ? route('cp.vendor-charges.update', $charge) : route('cp.vendor-charges.store') }}"
      class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4"
      x-data="vendorChargeForm()">
    @csrf
    @if($charge->exists) @method('PUT') @endif
    <div>
        <label class="text-sm">{{ $vendor->type->label() }} *</label>
        <select name="vendor_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" @disabled($charge->exists)>
            @foreach($vendors as $v)
                <option value="{{ $v->id }}" @selected(old('vendor_id', $charge->vendor_id)==$v->id)>{{ $v->name }} ({{ $v->type->label() }})</option>
            @endforeach
        </select>
        @if($charge->exists)<input type="hidden" name="vendor_id" value="{{ $charge->vendor_id }}">@endif
    </div>
    <div>
        <label class="text-sm">{{ $vendor->type->chargeDetailsLabel() }} *</label>
        <textarea name="title" rows="2" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('title', $charge->title) }}</textarea>
    </div>
    <div>
        <label class="text-sm">العملة *</label>
        <select name="currency_id" x-model="currencyId" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            @foreach($currencies as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
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
        <label class="text-sm">التاريخ *</label>
        <input type="date" name="charge_date" value="{{ old('charge_date', optional($charge->charge_date)->format('Y-m-d') ?? date('Y-m-d')) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>
    <div>
        <label class="text-sm">ملاحظات</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $charge->notes) }}</textarea>
    </div>
    <div class="flex gap-2">
        <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
        <a href="{{ $backUrl }}" class="px-5 py-2 rounded-xl border">إلغاء</a>
    </div>
</form>
@endsection
@push('scripts')
<script>
function vendorChargeForm() {
    return {
        ilsId: @json($ilsId),
        currencyId: @json((string) old('currency_id', $charge->fx_currency_id ?: $charge->currency_id ?: $ilsId)),
        amount: @json((string) old('source_amount', old('amount', $charge->source_amount ?: $charge->amount))),
        rate: @json((string) old('exchange_rate', $charge->formattedExchangeRate())),
        get isFx() { return String(this.currencyId) !== String(this.ilsId); },
        ilsTotal() {
            const amt = parseFloat(this.amount) || 0;
            const rate = parseFloat(this.rate) || 0;
            return (Math.round(amt * rate * 100) / 100).toFixed(2);
        }
    };
}
</script>
@endpush
