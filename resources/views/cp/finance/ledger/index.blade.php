@extends('cp.layout')
@section('title', 'دفتر الحركات')
@section('content')
<div class="space-y-4">
    <form class="flex flex-wrap gap-2">
        <select name="fund_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">الصندوق</option>@foreach($funds as $f)<option value="{{ $f->id }}" @selected(request('fund_id')==$f->id)>{{ $f->name }}</option>@endforeach</select>
        <select name="currency_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">العملة</option>@foreach($currencies as $c)<option value="{{ $c->id }}" @selected(request('currency_id')==$c->id)>{{ $c->code }}</option>@endforeach</select>
        <select name="transaction_type" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">النوع</option>@foreach($types as $t)<option value="{{ $t->value }}" @selected(request('transaction_type')==$t->value)>{{ $t->label() }}</option>@endforeach</select>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
        <button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
    </form>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">التاريخ</th><th class="px-3 py-2">الوصف</th><th class="px-3 py-2">النوع</th><th class="px-3 py-2">الصندوق</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">المبلغ</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($entries as $e)
                <tr>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $e->occurred_on->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">{{ $e->description }}@if($e->is_reversal)<span class="text-xs text-rose-600"> (إلغاء)</span>@endif</td>
                    <td class="px-3 py-2">{{ $e->transaction_type->label() }}</td>
                    <td class="px-3 py-2">{{ $e->fund->name }}</td>
                    <td class="px-3 py-2">{{ $e->paymentMethod->name }}</td>
                    <td class="px-3 py-2 font-bold {{ \App\Support\Money::isNegative($e->amount) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $e->currency->format($e->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-8 text-center text-slate-500">لا توجد حركات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
