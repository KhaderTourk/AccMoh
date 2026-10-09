@extends('cp.layout')
@section('title', $take->exists ? 'تعديل خصم/أخرى' : 'خصم/أخرى')
@section('content')
@php
    $ilsId = (string) ($ilsCurrency?->id ?? '');
    $partyType = old('party_type', $selectedPartyType ?? ($take->party_type ?: ($take->client_id ? 'client' : '')));
    $partyId = old('party_id', $selectedPartyId ?? ($take->party_id ?: $take->client_id));
    $initialPartyKey = old('party_key', ($partyType && $partyId) ? $partyType.':'.$partyId : '');
    $workers = ($parties['vendors'] ?? collect())->filter(fn ($v) => $v->type === \App\Enums\VendorType::Worker);
    $suppliers = ($parties['vendors'] ?? collect())->filter(fn ($v) => $v->type === \App\Enums\VendorType::Supplier);
    $backUrl = route('cp.clients.index');
    if ($partyType === 'client' && $partyId) {
        $backUrl = route('cp.clients.show', $partyId);
    } elseif ($partyType === 'person' && $partyId) {
        $backUrl = route('cp.persons.show', $partyId);
    } elseif ($partyType === 'vendor' && $partyId) {
        $selectedVendor = ($parties['vendors'] ?? collect())->firstWhere('id', (int) $partyId);
        $backUrl = $selectedVendor
            ? route('cp.'.$selectedVendor->type->routePrefix().'.show', $selectedVendor)
            : route('cp.suppliers.index');
    }
    $storedCategory = $take->exists ? \App\Enums\GoodsTakeCategory::fromStoredTitle($take->title) : null;
    $defaultCategory = ($partyType !== '' && $partyType !== 'client') ? \App\Enums\GoodsTakeCategory::Other->value : '';
    $category = old('category', $storedCategory?->value ?? $defaultCategory);
    $customTitle = old('title', $storedCategory === \App\Enums\GoodsTakeCategory::Other ? $take->title : '');
@endphp
<form method="post"
      action="{{ $take->exists ? route('cp.client-goods-takes.update', $take) : route('cp.client-goods-takes.store') }}"
      class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4"
      x-data="goodsTakeForm()">
    @csrf
    @if($take->exists) @method('PUT') @endif
    <div>
        <h2 class="text-xl font-bold">{{ $take->exists ? 'تعديل خصم/أخرى' : 'خصم/أخرى' }}</h2>
        <p class="text-sm text-slate-500 mt-1">يُخصم المبلغ من حساب الجهة دون تسجيل دفعة نقدية. عند اختيار «أخرى» يُكتب عنوان التصنيف. للمورد والموظف والشخص يكون «أخرى» محدداً ويمكن تغييره.</p>
    </div>
    <div>
        <label class="text-sm">الجهة *</label>
        <select name="party_key" x-model="partyKey" @change="onPartyChange()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" @disabled($take->exists)>
            <option value="">اختر الجهة</option>
            @if(($parties['clients'] ?? collect())->isNotEmpty())
                <optgroup label="الزبائن">
                    @foreach($parties['clients'] as $c)
                        <option value="client:{{ $c->id }}" @selected($initialPartyKey === 'client:'.$c->id)>{{ $c->directoryLabel() }}</option>
                    @endforeach
                </optgroup>
            @endif
            @if(($parties['persons'] ?? collect())->isNotEmpty())
                <optgroup label="الأشخاص">
                    @foreach($parties['persons'] as $p)
                        <option value="person:{{ $p->id }}" @selected($initialPartyKey === 'person:'.$p->id)>{{ $p->name }}</option>
                    @endforeach
                </optgroup>
            @endif
            @if($workers->isNotEmpty())
                <optgroup label="الموظفون">
                    @foreach($workers as $w)
                        <option value="vendor:{{ $w->id }}" @selected($initialPartyKey === 'vendor:'.$w->id)>{{ $w->name }}</option>
                    @endforeach
                </optgroup>
            @endif
            @if($suppliers->isNotEmpty())
                <optgroup label="الموردون">
                    @foreach($suppliers as $s)
                        <option value="vendor:{{ $s->id }}" @selected($initialPartyKey === 'vendor:'.$s->id)>{{ $s->name }}</option>
                    @endforeach
                </optgroup>
            @endif
        </select>
        @if($take->exists)<input type="hidden" name="party_key" value="{{ $initialPartyKey }}">@endif
    </div>
    <div>
        <label class="text-sm">التصنيف *</label>
        <select name="category" x-model="category" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            <option value="" disabled>اختر التصنيف</option>
            @foreach(\App\Enums\GoodsTakeCategory::cases() as $option)
                <option value="{{ $option->value }}" @selected($category === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </select>
    </div>
    <div x-show="category === 'other'" x-cloak>
        <label class="text-sm">العنوان *</label>
        <input type="text" name="title" value="{{ $customTitle }}" x-bind:required="category === 'other'" x-bind:disabled="category !== 'other'" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" placeholder="اكتب التصنيف">
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
        @include('cp.partials.date-field', ['name' => 'taken_on', 'value' => old('taken_on', optional($take->taken_on)->format('Y-m-d') ?? date('Y-m-d')), 'required' => true])
    </div>
    <div>
        <label class="text-sm">ملاحظات</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $take->notes) }}</textarea>
    </div>
    <div class="flex gap-2">
        <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
        <a href="{{ $backUrl }}" class="px-5 py-2 rounded-xl border">إلغاء</a>
    </div>
</form>
@endsection
@push('scripts')
<script>
function goodsTakeForm() {
    return {
        category: @json($category),
        partyKey: @json($initialPartyKey),
        onPartyChange() {
            const type = String(this.partyKey || '').split(':')[0];
            if (type && type !== 'client' && this.category === '') {
                this.category = 'other';
            }
        },
        ilsId: @json($ilsId),
        currencyId: @json((string) old('currency_id', $take->fx_currency_id ?: $take->currency_id ?: $ilsId)),
        amount: @json((string) old('source_amount', old('amount', $take->source_amount ?: $take->amount))),
        rate: @json((string) old('exchange_rate', $take->formattedExchangeRate())),
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
