@extends('cp.layout')
@section('title', $service->exists ? 'تعديل خدمة' : 'تسجيل خدمة')
@section('content')
@php
    $ilsId = (string) ($ilsCurrency?->id ?? '');
@endphp
<form method="post"
      action="{{ $service->exists ? route('cp.client-services.update', $service) : route('cp.client-services.store') }}"
      class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4"
      x-data="serviceForm()">
    @csrf
    @if($service->exists) @method('PUT') @endif
    <div>
        <label class="text-sm">الزبون *</label>
        <select name="client_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" @disabled($service->exists)>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $service->client_id)==$c->id)>{{ $c->name }}</option>@endforeach
        </select>
        @if($service->exists)<input type="hidden" name="client_id" value="{{ $service->client_id }}">@endif
    </div>
    <div>
        <label class="text-sm">الخدمة *</label>
        <select name="service_type_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            @foreach($serviceTypes as $t)
                <option value="{{ $t->id }}" @selected(old('service_type_id', $service->service_type_id)==$t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm">وصف الخدمة</label>
        <textarea name="description" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('description', $service->description) }}</textarea>
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
        <label class="text-sm">السعر *</label>
        <input type="number" step="0.01" min="0.01" name="amount" x-model="amount" x-bind:disabled="isFx" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>
    <div x-show="isFx" x-cloak class="space-y-3 rounded-xl border border-dashed p-4">
        <div>
            <label class="text-sm">السعر *</label>
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
        ilsId: @json($ilsId),
        currencyId: @json((string) old('currency_id', $service->fx_currency_id ?: $service->currency_id ?: $ilsId)),
        amount: @json((string) old('source_amount', old('amount', $service->source_amount ?: $service->amount))),
        rate: @json((string) old('exchange_rate', $service->formattedExchangeRate())),
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
