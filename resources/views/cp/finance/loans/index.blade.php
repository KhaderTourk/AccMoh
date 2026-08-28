@php
    $isCreditor = $direction->value === 'borrowed';
    $title = $isCreditor ? 'حركات دائن' : 'حركات مدين';
    $createDirection = $direction->value;
    $indexRoute = $isCreditor ? 'cp.family-loans.creditors' : 'cp.family-loans.debtors';
@endphp
@extends('cp.layout')
@section('title', $title)
@section('content')
<div class="space-y-4">
    <div class="flex justify-between flex-wrap gap-3">
        <form class="flex gap-2 flex-wrap items-end">
            <select name="family_member_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">كل الأفراد</option>
                @foreach($members as $m)
                    <option value="{{ $m->id }}" @selected(request('family_member_id')==$m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <label class="inline-flex items-center gap-1 text-sm"><input type="checkbox" name="open_only" value="1" @checked(request('open_only'))> المفتوحة فقط</label>
            <button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
        </form>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('cp.family-loans.repay', ['direction' => $createDirection]) }}" class="px-4 py-2 rounded-xl border">تسوية</a>
            <a href="{{ route('cp.family-loans.create', ['direction' => $createDirection]) }}" class="px-4 py-2 rounded-xl bg-primary text-white">إضافة</a>
        </div>
    </div>
    @include('cp.partials.date-range-shortcuts')
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">الشخص</th>
                <th class="px-3 py-2">المبلغ</th>
                <th class="px-3 py-2">المتبقي</th>
                <th class="px-3 py-2">الطريقة</th>
                <th class="px-3 py-2">التاريخ</th>
                <th class="px-3 py-2">الحالة</th>
                <th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($loans as $loan)
                <tr class="{{ $loan->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2"><a class="text-primary" href="{{ route('cp.family-members.show', $loan->familyMember) }}">{{ $loan->familyMember->name }}</a></td>
                    <td class="px-3 py-2">
                        {{ $loan->currency->format($loan->amount) }}
                        @if($loan->isFx())
                            <div class="text-xs text-slate-500">{{ $loan->fxCurrency?->format($loan->source_amount) }} × {{ $loan->formattedExchangeRate() }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $loan->currency->format($loan->remainingAmount()) }}</td>
                    <td class="px-3 py-2">{{ $loan->paymentMethod->name }}</td>
                    <td class="px-3 py-2">{{ $loan->loan_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">{{ $loan->is_reversed ? 'ملغاة' : $loan->status->label() }}</td>
                    <td class="px-3 py-2">
                        @unless($loan->is_reversed)
                        <div class="flex items-center gap-2 justify-end">
                            @if($loan->canEdit())
                                <a href="{{ route('cp.family-loans.edit', $loan) }}" class="text-primary text-xs">تعديل</a>
                            @endif
                            <form method="post" action="{{ route('cp.family-loans.destroy', $loan) }}" onsubmit="return confirm('حذف هذه الحركة؟')">
                                @csrf @method('DELETE')
                                <button class="text-rose-600 text-xs">حذف</button>
                            </form>
                        </div>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-8 text-center text-slate-500">لا توجد حركات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $loans->links() }}</div>
    </div>
</div>
@endsection
