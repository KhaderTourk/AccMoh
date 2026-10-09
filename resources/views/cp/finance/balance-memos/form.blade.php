@extends('cp.layout')
@section('title', $transfer ? 'ترحيل بين حسابين' : ($memo->exists ? 'تعديل رصيد مرحّل' : 'رصيد مرحّل'))
@section('content')
@php
    $partyType = old('party_type', $memo->party_type);
    $partyId = old('party_id', $memo->party_id);
    $initialPartyKey = old($transfer ? 'source_party_key' : 'party_key', ($partyType && $partyId) ? $partyType.':'.$partyId : '');
    $targetKey = old('target_party_key', '');
    $workers = ($parties['vendors'] ?? collect())->filter(fn ($v) => $v->type === \App\Enums\VendorType::Worker);
    $suppliers = ($parties['vendors'] ?? collect())->filter(fn ($v) => $v->type === \App\Enums\VendorType::Supplier);
    $backUrl = route('cp.dashboard');
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
    $action = $memo->exists
        ? route('cp.balance-memos.update', $memo)
        : ($transfer ? route('cp.balance-memos.transfer.store') : route('cp.balance-memos.store'));
@endphp
<form method="post" action="{{ $action }}" class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    @if($memo->exists) @method('PUT') @endif
    <div>
        <h2 class="text-xl font-bold">{{ $transfer ? 'ترحيل بين حسابين' : ($memo->exists ? 'تعديل رصيد مرحّل' : 'رصيد مرحّل') }}</h2>
        <p class="text-sm text-slate-500 mt-1">يسجّل من يدين لمن دون أن يتحرك الصندوق. الدفعة اللاحقة هي التي تغيّر النقد.</p>
    </div>

    @if($transfer && ! $memo->exists)
        <div>
            <label class="text-sm">من حساب *</label>
            @include('cp.finance.balance-memos.party-select', ['name' => 'source_party_key', 'selected' => $initialPartyKey, 'workers' => $workers, 'suppliers' => $suppliers, 'parties' => $parties])
        </div>
        <div>
            <label class="text-sm">إلى حساب *</label>
            @include('cp.finance.balance-memos.party-select', ['name' => 'target_party_key', 'selected' => $targetKey, 'workers' => $workers, 'suppliers' => $suppliers, 'parties' => $parties])
        </div>
    @else
        <div>
            <label class="text-sm">الجهة *</label>
            @include('cp.finance.balance-memos.party-select', ['name' => 'party_key', 'selected' => $initialPartyKey, 'workers' => $workers, 'suppliers' => $suppliers, 'parties' => $parties, 'locked' => $memo->exists])
        </div>
    @endif

    @unless($transfer)
        <div>
            <label class="text-sm">السبب *</label>
            <select name="reason" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach([\App\Enums\BalanceMemoReason::Opening, \App\Enums\BalanceMemoReason::YearRollover] as $reason)
                    <option value="{{ $reason->value }}" @selected(old('reason', $memo->reason?->value) === $reason->value)>{{ $reason->label() }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-500 mt-1">ترحيل السنة يُستخدم عندما السنة السابقة غير مسجّلة هنا. إن كانت حركاتها موجودة، فلتر الكشف يكفي.</p>
        </div>
    @endunless

    <div>
        <label class="text-sm">الاتجاه *</label>
        <select name="direction" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            @foreach(\App\Enums\BalanceMemoDirection::cases() as $direction)
                <option value="{{ $direction->value }}" @selected(old('direction', $memo->direction?->value) === $direction->value)>{{ $direction->label() }}</option>
            @endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1">عليه: نريد منه. له: نريد له. في الترحيل ينقص المصدر ويزيد الهدف بنفس الاتجاه.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="text-sm">المبلغ *</label>
            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $memo->amount) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-sm">العملة *</label>
            <select name="currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected((int) old('currency_id', $memo->currency_id ?: $ilsCurrency?->id) === $currency->id)>{{ $currency->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="text-sm">التاريخ *</label>
        @include('cp.partials.date-field', ['name' => 'memo_date', 'value' => old('memo_date', $memo->memo_date), 'required' => true])
    </div>

    <div>
        <label class="text-sm">ملاحظات</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $memo->notes) }}</textarea>
    </div>

    <div class="flex gap-2">
        <button class="cp-btn cp-btn-primary">حفظ</button>
        <a href="{{ $backUrl }}" class="cp-btn cp-btn-ghost">رجوع</a>
    </div>
</form>
@endsection
