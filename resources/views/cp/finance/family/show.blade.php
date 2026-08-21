@extends('cp.layout')
@section('title', $member->name)
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold">{{ $member->name }}</h2>
            <p class="text-slate-500 text-sm">{{ $member->relationship }} {{ $member->phone }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('cp.family-loans.create', ['family_member_id' => $member->id, 'direction' => 'borrowed']) }}" class="px-3 py-2 rounded-xl bg-primary text-white text-sm">اقتراض</a>
            <a href="{{ route('cp.family-loans.create', ['family_member_id' => $member->id, 'direction' => 'lent']) }}" class="px-3 py-2 rounded-xl border text-sm">إقراض</a>
            <a href="{{ route('cp.family-loans.repay', ['family_member_id' => $member->id]) }}" class="px-3 py-2 rounded-xl border text-sm">سداد</a>
        </div>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 dark:bg-rose-900/20 p-5">
            <h3 class="font-bold text-rose-700 dark:text-rose-300 mb-2">أنا مدين له</h3>
            @foreach($currencies as $c)
                <p class="text-lg font-extrabold">{{ $c->format($member->iOweAmount($c->id)) }}</p>
            @endforeach
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 p-5">
            <h3 class="font-bold text-emerald-700 dark:text-emerald-300 mb-2">هو مدين لي</h3>
            @foreach($currencies as $c)
                <p class="text-lg font-extrabold">{{ $c->format($member->theyOweAmount($c->id)) }}</p>
            @endforeach
        </div>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
        <h3 class="font-bold mb-2">صافي العلاقة (لكل عملة)</h3>
        @foreach($currencies as $c)
            @php
                $owe = $member->iOweAmount($c->id);
                $owed = $member->theyOweAmount($c->id);
                $net = \App\Support\Money::sub($owed, $owe);
            @endphp
            <p class="text-sm mb-1">{{ $c->code }}:
                @if(\App\Support\Money::isPositive($net)) صافٍ لي {{ $c->format($net) }}
                @elseif(\App\Support\Money::isNegative($net)) صافٍ عليّ {{ $c->format(\App\Support\Money::abs($net)) }}
                @else متعادل
                @endif
            </p>
        @endforeach
    </div>
    <section class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <div class="px-4 py-3 border-b font-bold">القروض</div>
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/40"><tr><th class="px-3 py-2">الاتجاه</th><th class="px-3 py-2">الأصل</th><th class="px-3 py-2">المتبقي</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2">الحالة</th></tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @foreach($member->loans as $loan)
                <tr class="{{ $loan->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2">{{ $loan->direction->label() }}</td>
                    <td class="px-3 py-2">{{ $loan->currency->format($loan->amount) }}</td>
                    <td class="px-3 py-2">{{ $loan->currency->format($loan->remainingAmount()) }}</td>
                    <td class="px-3 py-2">{{ $loan->loan_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">{{ $loan->status->label() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
@endsection
