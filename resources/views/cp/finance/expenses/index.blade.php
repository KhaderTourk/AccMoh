@extends('cp.layout')
@section('title', 'المصروفات')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between flex-wrap gap-3">
        <form class="flex flex-wrap gap-2 items-end">
            <select name="fund_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">الصندوق</option>@foreach($funds as $f)<option value="{{ $f->id }}" @selected(request('fund_id')==$f->id)>{{ $f->name }}</option>@endforeach</select>
            @include('cp.partials.date-range-fields')
            <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث في الجهة أو الملاحظات" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
            @include('cp.partials.date-range-shortcuts')
        </form>
        <a href="{{ route('cp.expenses.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">مصروف جديد</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">الجهة</th><th class="px-3 py-2">المستلم</th><th class="px-3 py-2">الصندوق</th><th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($expenses as $e)
                <tr class="{{ $e->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2">
                        {{ $e->description }}
                        @if($e->vendor)
                            <div class="text-xs text-slate-500">{{ $e->vendor->type->label() }}: {{ $e->vendor->name }}</div>
                        @endif
                        @include('cp.partials.note-line', ['notes' => $e->notes])
                    </td>
                    <td class="px-3 py-2">{{ $e->payee ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $e->fund->name }}</td>
                    <td class="px-3 py-2">{{ $e->currency->format($e->amount) }}</td>
                    <td class="px-3 py-2">{{ $e->paymentMethod->name }}</td>
                    <td class="px-3 py-2">{{ $e->expense_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">
                        @unless($e->is_reversed)
                        <form method="post" action="{{ route('cp.expenses.reverse', $e) }}" onsubmit="return confirm('إلغاء المصروف؟')">@csrf<button class="text-rose-600 text-xs">إلغاء</button></form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-8 text-center text-slate-500">لا توجد مصروفات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $expenses->links() }}</div>
    </div>
</div>
@endsection
