@extends('cp.layout')
@section('title', 'سجل حركات الحساب')
@section('content')
@php
    $filterCount = collect(['q', 'from', 'to', '_preset', 'fund_id', 'currency_id', 'transaction_type', 'payment_method_id'])->filter(fn ($key) => filled(request($key)))->count();
@endphp
<div class="space-y-4">
    @component('cp.partials.filter-panel', ['count' => $filterCount])
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">بحث</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="الوصف أو الملاحظات" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">نوع الحركة</label>
            <select name="transaction_type" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($types as $t)<option value="{{ $t->value }}" @selected(request('transaction_type')==$t->value)>{{ $t->label() }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">الدرج</label>
            <select name="fund_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($funds as $f)<option value="{{ $f->id }}" @selected(request('fund_id')==$f->id)>{{ $f->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">طريقة الدفع</label>
            <select name="payment_method_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($paymentMethods as $m)<option value="{{ $m->id }}" @selected(request('payment_method_id')==$m->id)>{{ $m->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">العملة</label>
            <select name="currency_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($currencies as $c)<option value="{{ $c->id }}" @selected(request('currency_id')==$c->id)>{{ $c->code }}</option>@endforeach
            </select>
        </div>
        @include('cp.partials.date-range-fields')
        @slot('footer')
            @include('cp.partials.date-range-shortcuts')
        @endslot
    @endcomponent

    @if($totals->isNotEmpty())
    <div class="rounded-2xl border bg-white dark:bg-slate-800 p-4">
        <h3 class="font-bold mb-2">صافي أثر الحركات بعد التصفية</h3>
        <div class="flex flex-wrap gap-4">
            @foreach($totals as $row)
                @if($row['currency'])
                    <p class="text-lg font-extrabold {{ \App\Support\Money::isNegative($row['total']) ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $row['currency']->format($row['total']) }}
                    </p>
                @endif
            @endforeach
        </div>
    </div>
    @endif
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-x-auto">
        <table class="w-full text-sm text-right min-w-[860px]">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-3">التاريخ</th>
                <th class="px-3 py-3">الوقت</th>
                <th class="px-3 py-3">نوع الحركة</th>
                <th class="px-3 py-3">البيان</th>
                <th class="px-3 py-3">الطرف</th>
                <th class="px-3 py-3">الدرج</th>
                <th class="px-3 py-3">الطريقة</th>
                <th class="px-3 py-3">المبلغ</th>
                <th class="px-3 py-3">بواسطة</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($entries as $e)
                <tr class="{{ \App\Support\Money::isNegative($e->amount) ? 'bg-rose-50/70 dark:bg-rose-900/20' : 'bg-emerald-50/70 dark:bg-emerald-900/20' }}">
                    <td class="px-3 py-3 whitespace-nowrap font-medium">{{ $e->occurred_on->format('Y-m-d') }}</td>
                    <td class="px-3 py-3 whitespace-nowrap text-slate-500">{{ $e->recordedAt() }}</td>
                    <td class="px-3 py-3 whitespace-nowrap">
                        <span class="cp-type-pill {{ $e->transaction_type->badgeClass() }}">{{ $e->transaction_type->label() }}</span>
                    </td>
                    <td class="px-3 py-3">
                        {{ $e->description }}
                        @if($e->is_reversal)<span class="text-xs text-rose-600"> (إلغاء)</span>@endif
                        @include('cp.partials.note-line', ['notes' => $e->notes])
                    </td>
                    <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ $e->counterpartyLabel() ?: '—' }}</td>
                    <td class="px-3 py-3">{{ $e->fund->name }}</td>
                    <td class="px-3 py-3">{{ $e->paymentMethod->name }}</td>
                    <td class="px-3 py-3 font-bold {{ \App\Support\Money::isNegative($e->amount) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $e->currency->format($e->amount) }}</td>
                    <td class="px-3 py-3 text-xs text-slate-500">{{ $e->creator?->name ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="cp-empty">لا توجد حركات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
