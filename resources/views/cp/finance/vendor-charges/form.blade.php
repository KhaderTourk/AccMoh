@extends('cp.layout')
@section('title', $vendor->type->chargeFormTitle($charge->exists))
@section('content')
@php
    $usdId = $usdCurrency?->id;
    $isExistingFx = $charge->exists && ($charge->isFx() || $charge->currency?->code === 'USD');
    $oldFx = old('requires_fx');
    $requiresFx = $oldFx === null ? $isExistingFx : filter_var($oldFx, FILTER_VALIDATE_BOOLEAN);
    $usdAmount = old('source_amount', $charge->source_amount ?? ($isExistingFx && ! $charge->isFx() ? $charge->amount : ''));
    $rate = old('exchange_rate', $charge->formattedExchangeRate());
    $ilsAmount = old('amount', $isExistingFx && ! $charge->isFx() ? '' : $charge->amount);
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
        <textarea name="title" x-model="title" rows="2" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></textarea>
    </div>

    <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="hidden" name="requires_fx" value="0">
        <input type="checkbox" name="requires_fx" value="1" x-model="requiresFx" class="rounded border">
        يتطلب تحويل بين العملات
    </label>

    <div x-show="!requiresFx">
        <label class="text-sm">السعر / شيكل *</label>
        <input type="number" step="0.01" min="0.01" name="amount" x-model="ilsAmount" x-bind:disabled="requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>

    <div x-show="requiresFx" x-cloak class="space-y-3 rounded-xl border border-dashed p-4">
        <div>
            <label class="text-sm">السعر / دولار *</label>
            <input type="number" step="0.01" min="0.01" name="source_amount" x-model="usdAmount" x-bind:disabled="!requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-sm">سعر الدولار *</label>
            <input type="number" step="0.0001" min="0.0001" name="exchange_rate" x-model="rate" x-bind:disabled="!requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" placeholder="مثال: 3.65">
        </div>
        <div>
            <label class="text-sm">القيمة الإجمالية / شيكل</label>
            <input type="text" :value="ilsTotal()" readonly tabindex="-1" class="w-full rounded-xl border px-3 py-2 bg-slate-50 dark:bg-slate-700/60">
            <input type="hidden" name="amount" :value="ilsTotal()" x-bind:disabled="!requiresFx">
        </div>
        <p class="text-xs text-slate-500">الإجمالي = السعر بالدولار × سعر الدولار. يترصد المبلغ بالشيكل ضمن المستحقات.</p>
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
        title: @json((string) old('title', $charge->title)),
        requiresFx: @json($requiresFx),
        usdAmount: @json((string) $usdAmount),
        rate: @json((string) $rate),
        ilsAmount: @json((string) $ilsAmount),
        usdId: @json((string) $usdId),
        ilsTotal() {
            const usd = parseFloat(this.usdAmount) || 0;
            const rate = parseFloat(this.rate) || 0;
            return (Math.round(usd * rate * 100) / 100).toFixed(2);
        }
    };
}
</script>
@endpush
