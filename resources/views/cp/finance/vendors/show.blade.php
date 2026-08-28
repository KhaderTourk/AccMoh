@extends('cp.layout')
@section('title', $vendor->name)
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap justify-between gap-3">
        <div>
            <p class="text-xs text-slate-500">{{ $type->label() }}</p>
            <h2 class="text-2xl font-bold">{{ $vendor->name }}</h2>
            <p class="text-slate-500 text-sm">{{ $vendor->phone }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cp.expenses.create', ['vendor_id' => $vendor->id, 'fund_id' => $businessFundId]) }}" class="px-3 py-2 rounded-xl bg-primary text-white text-sm">مصروف جديد</a>
            <a href="{{ route('cp.'.$type->routePrefix().'.edit', $vendor) }}" class="px-3 py-2 rounded-xl border text-sm">تعديل</a>
        </div>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        @foreach($currencies as $c)
            @php $paid = $vendor->paidAmount($c->id); @endphp
            @if(!\App\Support\Money::isZero($paid))
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
                <p class="text-sm text-slate-500">إجمالي المصروفات — {{ $c->name }}</p>
                <p class="text-2xl font-extrabold">{{ $c->format($paid) }}</p>
            </div>
            @endif
        @endforeach
    </div>
    <section class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <div class="px-4 py-3 border-b font-bold">سجل المصروفات</div>
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                <th class="px-3 py-2">الجهة</th><th class="px-3 py-2">المستلم</th><th class="px-3 py-2">التصنيف</th>
                <th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">التاريخ</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($vendor->expenses as $e)
                <tr class="{{ $e->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2">{{ $e->description }}</td>
                    <td class="px-3 py-2">{{ $e->payee ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $e->category?->name ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $e->currency->format($e->amount) }}</td>
                    <td class="px-3 py-2">{{ $e->paymentMethod->name }}</td>
                    <td class="px-3 py-2">{{ $e->expense_date->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-center text-slate-500">لا توجد مصروفات.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
