@extends('cp.layout')
@section('title', 'التحويلات')
@section('content')
<div class="space-y-4">
    <div class="flex justify-end"><a href="{{ route('cp.transfers.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">تحويل / صرف جديد</a></div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">الصندوق</th><th class="px-3 py-2">من</th><th class="px-3 py-2">إلى</th>
                <th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($transfers as $t)
                <tr class="{{ $t->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2">{{ $t->fund->name }}</td>
                    <td class="px-3 py-2">{{ $t->fromMethod->name }} / {{ $t->currency->code }}</td>
                    <td class="px-3 py-2">{{ $t->toMethod->name }} / {{ ($t->toCurrency ?? $t->currency)->code }}</td>
                    <td class="px-3 py-2">
                        {{ $t->currency->format($t->amount) }}
                        @if($t->isFx())
                            <div class="text-xs text-slate-500">→ {{ $t->toCurrency->format($t->to_amount) }}</div>
                        @endif
                        @if(\App\Support\Money::isPositive($t->fee_amount))
                            <div class="text-xs text-amber-600">رسوم {{ $t->currency->format($t->fee_amount) }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $t->isFx() ? $t->exchange_rate : '—' }}</td>
                    <td class="px-3 py-2">{{ $t->transfer_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">@unless($t->is_reversed)<form method="post" action="{{ route('cp.transfers.reverse', $t) }}" onsubmit="return confirm('إلغاء التحويل؟')">@csrf<button class="text-rose-600 text-xs">إلغاء</button></form>@endunless</td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-8 text-center text-slate-500">لا توجد تحويلات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $transfers->links() }}</div>
    </div>
</div>
@endsection
