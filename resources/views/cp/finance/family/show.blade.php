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
            <a href="{{ route('cp.family-loans.create', ['family_member_id' => $member->id, 'direction' => 'borrowed']) }}" class="px-3 py-2 rounded-xl bg-primary text-white text-sm">دائن</a>
            <a href="{{ route('cp.family-loans.create', ['family_member_id' => $member->id, 'direction' => 'lent']) }}" class="px-3 py-2 rounded-xl border text-sm">مدين</a>
            <a href="{{ route('cp.family-loans.repay', ['family_member_id' => $member->id]) }}" class="px-3 py-2 rounded-xl border text-sm">تسوية</a>
            <a href="{{ route('cp.family-members.export-pdf', $member) }}" class="px-3 py-2 rounded-xl border text-sm">تصدير PDF</a>
        </div>
    </div>
    @include('cp.partials.note-card', ['notes' => $member->notes])
    <div class="grid md:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 dark:bg-rose-900/20 p-5">
            <h3 class="font-bold text-rose-700 dark:text-rose-300 mb-2">دائن (عليّ)</h3>
            @foreach($currencies as $c)
                <p class="text-lg font-extrabold">{{ $c->format($member->iOweAmount($c->id)) }}</p>
            @endforeach
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 p-5">
            <h3 class="font-bold text-emerald-700 dark:text-emerald-300 mb-2">مدين (لي)</h3>
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
        <div class="px-4 py-3 border-b font-bold">دائن ومدين</div>
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                <th class="px-3 py-2">النوع</th><th class="px-3 py-2">الأصل</th><th class="px-3 py-2">المتبقي</th>
                <th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2">الحالة</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @foreach($member->loans as $loan)
                <tr class="{{ $loan->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2">
                        {{ $loan->direction->label() }}
                        @include('cp.partials.note-line', ['notes' => $loan->notes])
                    </td>
                    <td class="px-3 py-2">{{ $loan->currency->format($loan->amount) }}
                        @if($loan->isFx())
                            <div class="text-xs text-slate-500">{{ $loan->fxCurrency?->format($loan->source_amount) }} × {{ $loan->formattedExchangeRate() }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $loan->currency->format($loan->remainingAmount()) }}</td>
                    <td class="px-3 py-2">{{ $loan->paymentMethod->name }}</td>
                    <td class="px-3 py-2">{{ $loan->loan_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">{{ $loan->status->label() }}</td>
                    <td class="px-3 py-2">
                        @unless($loan->is_reversed)
                        <div class="flex gap-2 justify-end">
                            @if($loan->canEdit())
                                <a href="{{ route('cp.family-loans.edit', $loan) }}" class="text-primary text-xs">تعديل</a>
                            @endif
                            <form method="post" action="{{ route('cp.family-loans.destroy', $loan) }}" onsubmit="return confirm('حذف الحركة؟')">@csrf @method('DELETE')<button class="text-rose-600 text-xs">حذف</button></form>
                        </div>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <section class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <div class="px-4 py-3 border-b font-bold">التسويات</div>
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                <th class="px-3 py-2">النوع</th><th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($member->repayments as $repayment)
                <tr class="{{ $repayment->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2">
                        {{ $repayment->direction->label() }}
                        @include('cp.partials.note-line', ['notes' => $repayment->notes])
                    </td>
                    <td class="px-3 py-2">{{ $repayment->currency->format($repayment->amount) }}</td>
                    <td class="px-3 py-2">{{ $repayment->paymentMethod->name }}</td>
                    <td class="px-3 py-2">{{ $repayment->repayment_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">
                        @unless($repayment->is_reversed)
                        <form method="post" action="{{ route('cp.family-loan-repayments.reverse', $repayment) }}" onsubmit="return confirm('إلغاء التسوية؟')">@csrf<button class="text-rose-600 text-xs">إلغاء</button></form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-500">لا توجد تسويات.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
