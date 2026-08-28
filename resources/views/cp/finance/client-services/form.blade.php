@extends('cp.layout')
@section('title', $service->exists ? 'تعديل خدمة' : 'تسجيل خدمة')
@section('content')
@php
    $usdId = $usdCurrency?->id;
    $isExistingFx = $service->exists && ($service->isFx() || $service->currency?->code === 'USD');
    $oldFx = old('requires_fx');
    $requiresFx = $oldFx === null ? $isExistingFx : filter_var($oldFx, FILTER_VALIDATE_BOOLEAN);
    $usdAmount = old('source_amount', $service->source_amount ?? ($isExistingFx && ! $service->isFx() ? $service->amount : ''));
    $rate = old('exchange_rate', $service->formattedExchangeRate());
    $ilsAmount = old('amount', $isExistingFx && ! $service->isFx() ? '' : $service->amount);
@endphp
<form method="post"
      action="{{ $service->exists ? route('cp.client-services.update', $service) : route('cp.client-services.store') }}"
      class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4"
      x-data="serviceForm()">
    @csrf
    @if($service->exists) @method('PUT') @endif
    <div>
        <label class="text-sm">العميل *</label>
        <select name="client_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" @disabled($service->exists)>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $service->client_id)==$c->id)>{{ $c->name }}</option>@endforeach
        </select>
        @if($service->exists)<input type="hidden" name="client_id" value="{{ $service->client_id }}">@endif
    </div>
    <div>
        <label class="text-sm">نوع الخدمة (قالب)</label>
        <select name="service_type_id" x-model="typeId" @change="applyType()" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            <option value="">مخصص</option>
            @foreach($serviceTypes as $t)
                <option value="{{ $t->id }}" data-name="{{ $t->name }}" data-price="{{ $t->default_price }}" data-currency="{{ $t->default_currency_id }}">{{ $t->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm">عنوان الخدمة *</label>
        <input name="title" x-model="title" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>

    <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="hidden" name="requires_fx" value="0">
        <input type="checkbox" name="requires_fx" value="1" x-model="requiresFx" class="rounded border">
        خدمة تتطلب تحويل بين العملات
    </label>

    <div x-show="!requiresFx">
        <label class="text-sm">سعر الخدمة / شيكل *</label>
        <input type="number" step="0.01" min="0.01" name="amount" x-model="ilsAmount" x-bind:disabled="requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>

    <div x-show="requiresFx" x-cloak class="space-y-3 rounded-xl border border-dashed p-4">
        <div>
            <label class="text-sm">سعر الخدمة / دولار *</label>
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
        <p class="text-xs text-slate-500">العملة التي يدفع بها العملاء هي الشيكل. الإجمالي = سعر الخدمة بالدولار × سعر الدولار.</p>
    </div>

    <div>
        <label class="text-sm">التاريخ *</label>
        <input type="date" name="service_date" value="{{ old('service_date', optional($service->service_date)->format('Y-m-d') ?? date('Y-m-d')) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>
    <div>
        <label class="text-sm">ملاحظات</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $service->notes) }}</textarea>
    </div>
    <div class="flex gap-2">
        <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
        <a href="{{ route('cp.client-services.index') }}" class="px-5 py-2 rounded-xl border">إلغاء</a>
    </div>
</form>
@endsection
@push('scripts')
<script>
function serviceForm() {
    return {
        typeId: @json((string) old('service_type_id', $service->service_type_id)),
        title: @json((string) old('title', $service->title)),
        requiresFx: @json($requiresFx),
        usdAmount: @json((string) $usdAmount),
        rate: @json((string) $rate),
        ilsAmount: @json((string) $ilsAmount),
        usdId: @json((string) $usdId),
        ilsTotal() {
            const usd = parseFloat(this.usdAmount) || 0;
            const rate = parseFloat(this.rate) || 0;
            return (Math.round(usd * rate * 100) / 100).toFixed(2);
        },
        applyType() {
            const select = this.$el.querySelector('select[name=service_type_id]');
            const opt = select?.selectedOptions[0];
            if (!opt || !opt.value) return;
            if (opt.dataset.name) this.title = opt.dataset.name;
            if (String(opt.dataset.currency) === String(this.usdId)) {
                this.requiresFx = true;
                this.usdAmount = opt.dataset.price || this.usdAmount;
            } else if (opt.dataset.price) {
                this.requiresFx = false;
                this.ilsAmount = opt.dataset.price;
            }
        }
    };
}
</script>
@endpush
