@extends('cp.layout')
@section('title', 'الصناديق والأرصدة')
@section('content')
<div class="space-y-6">
    <div class="overflow-x-auto rounded-2xl bg-white dark:bg-slate-800 border shadow-sm">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-4 py-3">الصندوق / الطريقة</th>
                    @foreach($snapshot['currencies'] as $currency)
                        <th class="px-4 py-3">{{ $currency->name }} ({{ $currency->code }})</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-slate-700">
                @foreach($snapshot['funds'] as $fund)
                    <tr class="bg-slate-50/70 dark:bg-slate-700/30 font-bold">
                        <td class="px-4 py-3">{{ $fund->name }}</td>
                        @foreach($snapshot['currencies'] as $currency)
                            <td class="px-4 py-3">{{ $currency->format($snapshot['fundTotals'][$fund->id][$currency->id] ?? 0) }}</td>
                        @endforeach
                    </tr>
                    @foreach($snapshot['methods'] as $method)
                    <tr>
                        <td class="px-4 py-2 ps-8 text-slate-600">{{ $method->name }}</td>
                        @foreach($snapshot['currencies'] as $currency)
                            <td class="px-4 py-2">{{ $currency->format($snapshot['cells'][$fund->id][$method->id][$currency->id] ?? 0) }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                @endforeach
                <tr class="font-extrabold bg-primary/5">
                    <td class="px-4 py-3">الإجمالي</td>
                    @foreach($snapshot['currencies'] as $currency)
                        <td class="px-4 py-3">{{ $currency->format($snapshot['grand'][$currency->id] ?? 0) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <section class="rounded-2xl bg-white dark:bg-slate-800 border p-6">
        <h2 class="font-bold mb-4 flex items-center gap-2"><span class="material-symbols-outlined text-primary">add_card</span> رصيد افتتاحي</h2>
        <form method="post" action="{{ route('cp.balances.opening') }}" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
            @csrf
            <div>
                <label class="text-xs text-slate-500">الصندوق</label>
                <select name="fund_id" required class="cp-input w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    @foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">طريقة الدفع</label>
                <select name="payment_method_id" required class="cp-input w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    @foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">العملة</label>
                <select name="currency_id" required class="cp-input w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    @foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">المبلغ</label>
                <input type="number" step="0.01" min="0.01" name="amount" required class="cp-input w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs text-slate-500">التاريخ</label>
                <input type="date" name="occurred_on" value="{{ old('occurred_on', date('Y-m-d')) }}" required class="cp-input w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <button class="px-4 py-2 rounded-xl bg-primary text-white font-medium">تسجيل</button>
            <div class="md:col-span-3 lg:col-span-3">
                <label class="text-xs text-slate-500">الوصف</label>
                <input type="text" name="description" value="{{ old('description') }}" placeholder="رصيد افتتاحي" class="cp-input w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div class="md:col-span-3 lg:col-span-3">
                <label class="text-xs text-slate-500">ملاحظة</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="cp-input w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
        </form>
    </section>
</div>
@endsection
