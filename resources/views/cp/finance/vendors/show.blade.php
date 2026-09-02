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
            <a href="{{ route('cp.vendor-charges.create', ['vendor_id' => $vendor->id]) }}" class="px-3 py-2 rounded-xl bg-primary text-white text-sm">{{ $type->chargeAction() }}</a>
            <a href="{{ route('cp.expenses.create', ['vendor_id' => $vendor->id, 'fund_id' => $businessFundId]) }}" class="px-3 py-2 rounded-xl border text-sm">سداد</a>
            <a href="{{ route('cp.'.$type->routePrefix().'.edit', $vendor) }}" class="px-3 py-2 rounded-xl border text-sm">تعديل</a>
            <form method="post" action="{{ route('cp.'.$type->routePrefix().'.destroy', $vendor) }}" onsubmit="return confirm('حذف/أرشفة {{ $type->label() }}؟')">
                @csrf @method('DELETE')
                <button type="submit" class="px-3 py-2 rounded-xl border border-rose-200 text-rose-600 text-sm">حذف</button>
            </form>
        </div>
    </div>
    @include('cp.partials.note-card', ['notes' => $vendor->notes])

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($currencies as $currency)
            @php
                $billed = $vendor->billedAmount($currency->id);
                $paid = $vendor->paidAmount($currency->id);
                $due = $vendor->outstandingAmount($currency->id);
            @endphp
            @if(!\App\Support\Money::isZero($billed) || !\App\Support\Money::isZero($paid))
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
                <h3 class="font-bold mb-3">{{ $currency->name }}</h3>
                <div class="space-y-1 text-sm">
                    <p>{{ $type->billedLabel() }}: <strong>{{ $currency->format($billed) }}</strong></p>
                    <p>المدفوع: <strong class="text-emerald-600">{{ $currency->format($paid) }}</strong></p>
                    @if(\App\Support\Money::isNegative($due))
                        <p>مدفوع مقدماً: <strong class="text-emerald-600">{{ $currency->format(\App\Support\Money::abs($due)) }}</strong></p>
                    @else
                        <p>{{ $type->outstandingLabel() }}: <strong class="text-amber-600">{{ $currency->format($due) }}</strong></p>
                    @endif
                </div>
            </div>
            @endif
        @endforeach
    </div>

    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-bold text-lg">{{ $type->chargesHeading() }}</h3>
            <a href="{{ route('cp.vendor-charges.create', ['vendor_id' => $vendor->id]) }}" class="text-sm text-primary">إضافة</a>
        </div>
        <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                    <th class="px-3 py-2">التفاصيل</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
                </tr></thead>
                <tbody class="divide-y dark:divide-slate-700">
                @forelse($vendor->charges as $charge)
                    <tr>
                        <td class="px-3 py-2">
                            {{ $charge->title }}
                            @include('cp.partials.note-line', ['notes' => $charge->notes])
                        </td>
                        <td class="px-3 py-2">
                            {{ $charge->currency->format($charge->amount) }}
                            @if($charge->isFx())
                                <div class="text-xs text-slate-500">{{ $charge->fxCurrency?->format($charge->source_amount) }} × {{ $charge->formattedExchangeRate() }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $charge->charge_date->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-1 justify-end">
                                <a href="{{ route('cp.vendor-charges.edit', $charge) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                                <form method="post" action="{{ route('cp.vendor-charges.destroy', $charge) }}" onsubmit="return confirm('حذف هذا السجل؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-slate-500">لا توجد سجلات.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-bold text-lg">الدفعات</h3>
            <a href="{{ route('cp.expenses.create', ['vendor_id' => $vendor->id, 'fund_id' => $businessFundId]) }}" class="text-sm text-primary">إضافة سداد</a>
        </div>
        <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                    <th class="px-3 py-2">الجهة</th><th class="px-3 py-2">المستلم</th><th class="px-3 py-2">التصنيف</th>
                    <th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">التاريخ</th>
                </tr></thead>
                <tbody class="divide-y dark:divide-slate-700">
                @forelse($vendor->expenses as $e)
                    <tr class="{{ $e->is_reversed ? 'opacity-40' : '' }}">
                        <td class="px-3 py-2">
                            {{ $e->description }}
                            @include('cp.partials.note-line', ['notes' => $e->notes])
                        </td>
                        <td class="px-3 py-2">{{ $e->payee ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $e->category?->name ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $e->currency->format($e->amount) }}</td>
                        <td class="px-3 py-2">{{ $e->paymentMethod->name }}</td>
                        <td class="px-3 py-2">{{ $e->expense_date->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-slate-500">لا توجد دفعات.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
