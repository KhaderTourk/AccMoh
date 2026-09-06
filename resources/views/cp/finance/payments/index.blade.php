@extends('cp.layout')
@section('title', $direction->label())
@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row justify-between gap-3">
        <form class="flex flex-wrap gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث..." class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            @include('cp.partials.date-range-fields')
            <button class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
        </form>
        <a href="{{ route('cp.payments.create', $direction->value) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white">
            <span class="material-symbols-outlined">add</span> {{ $direction->label() }}
        </a>
    </div>
    <div class="rounded-2xl bg-white dark:bg-slate-800 border overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">التاريخ</th><th class="px-3 py-2">الاسم</th><th class="px-3 py-2">الدرج</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">المبلغ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($payments as $p)
                <tr class="{{ $p->is_reversed ? 'opacity-40' : $p->direction->rowClass() }}">
                    <td class="px-3 py-2 whitespace-nowrap">{{ $p->occurred_on->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">
                        {{ $p->name }}
                        @include('cp.partials.note-line', ['notes' => $p->notes])
                    </td>
                    <td class="px-3 py-2">{{ $p->fund->name }}</td>
                    <td class="px-3 py-2">{{ $p->paymentMethod->name }}</td>
                    <td class="px-3 py-2 font-bold {{ $p->direction->colorClass() }}">
                        {{ $p->currency->format($p->amount) }}
                        @if($p->isFx())
                            <div class="text-xs font-normal text-slate-500">{{ $p->fxCurrency?->format($p->source_amount) }} × {{ $p->formattedExchangeRate() }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">
                        @unless($p->is_reversed)
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('cp.payments.edit', $p) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                            <form method="post" action="{{ route('cp.payments.destroy', $p) }}" onsubmit="return confirm('حذف هذه الدفعة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                            </form>
                        </div>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-8 text-center text-slate-500">لا توجد دفعات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $payments->links() }}</div>
    </div>
</div>
@endsection
