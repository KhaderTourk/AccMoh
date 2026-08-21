@extends('cp.layout')
@section('title', $service->exists ? 'تعديل خدمة' : 'تسجيل خدمة')
@section('content')
<form method="post" action="{{ $service->exists ? route('cp.client-services.update', $service) : route('cp.client-services.store') }}" class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4"
      x-data="{ typeId: '{{ old('service_type_id', $service->service_type_id) }}', title: '{{ old('title', $service->title) }}', amount: '{{ old('amount', $service->amount) }}', currencyId: '{{ old('currency_id', $service->currency_id ?? $currencies->first()?->id) }}' }"
      @change.type="applyType()">
    @csrf
    @if($service->exists) @method('PUT') @endif
    <div>
        <label class="text-sm">العميل *</label>
        <select name="client_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $service->client_id)==$c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="text-sm">نوع الخدمة (قالب)</label>
        <select name="service_type_id" x-model="typeId" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            <option value="">مخصص</option>
            @foreach($serviceTypes as $t)
                <option value="{{ $t->id }}" data-name="{{ $t->name }}" data-price="{{ $t->default_price }}" data-currency="{{ $t->default_currency_id }}">{{ $t->name }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="text-sm">عنوان الخدمة *</label><input name="title" x-model="title" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm">السعر *</label><input type="number" step="0.01" min="0.01" name="amount" x-model="amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">العملة *</label>
            <select name="currency_id" x-model="currencyId" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm">التاريخ *</label><input type="date" name="service_date" value="{{ old('service_date', optional($service->service_date)->format('Y-m-d') ?? date('Y-m-d')) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">الحالة</label>
            <select name="status" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($statuses as $st)<option value="{{ $st->value }}" @selected(old('status', $service->status?->value)==$st->value)>{{ $st->label() }}</option>@endforeach
            </select>
        </div>
    </div>
    <div><label class="text-sm">الوصف</label><textarea name="description" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('description', $service->description) }}</textarea></div>
    <div><label class="text-sm">ملاحظات</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $service->notes) }}</textarea></div>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
</form>
@endsection
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {});
function applyTypeFromSelect(root) {
    // handled inline via alpine below
}
</script>
<script>
document.querySelector('[x-data]')?.__x; // noop
</script>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('select[name=service_type_id]');
    select?.addEventListener('change', function() {
        const opt = this.selectedOptions[0];
        if (!opt || !opt.value) return;
        const title = document.querySelector('input[name=title]');
        const amount = document.querySelector('input[name=amount]');
        const currency = document.querySelector('select[name=currency_id]');
        if (opt.dataset.name) title.value = opt.dataset.name;
        if (opt.dataset.price) amount.value = opt.dataset.price;
        if (opt.dataset.currency) currency.value = opt.dataset.currency;
    });
});
</script>
@endpush
