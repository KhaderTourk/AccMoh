@extends('cp.layout')
@section('title', 'دائن ومدين')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between"><form class="flex gap-2 flex-wrap">
        <select name="direction" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">النوع</option>@foreach($directions as $d)<option value="{{ $d->value }}" @selected(request('direction')==$d->value)>{{ $d->label() }}</option>@endforeach</select>
        <label class="inline-flex items-center gap-1 text-sm"><input type="checkbox" name="open_only" value="1" @checked(request('open_only'))> المفتوحة فقط</label>
        <button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
    </form>
    <div class="flex gap-2">
        <a href="{{ route('cp.family-loans.repay') }}" class="px-4 py-2 rounded-xl border">دائن</a>
        <a href="{{ route('cp.family-loans.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">تسجيل</a>
    </div></div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">الشخص</th><th class="px-3 py-2">النوع</th><th class="px-3 py-2">المبلغ</th>
                <th class="px-3 py-2">المتبقي</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($loans as $loan)
                <tr class="{{ $loan->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2"><a class="text-primary" href="{{ route('cp.family-members.show', $loan->familyMember) }}">{{ $loan->familyMember->name }}</a></td>
                    <td class="px-3 py-2">{{ $loan->direction->label() }}</td>
                    <td class="px-3 py-2">{{ $loan->currency->format($loan->amount) }}</td>
                    <td class="px-3 py-2">{{ $loan->currency->format($loan->remainingAmount()) }}</td>
                    <td class="px-3 py-2">{{ $loan->paymentMethod->name }}</td>
                    <td class="px-3 py-2">{{ $loan->loan_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">
                        @unless($loan->is_reversed)
                        <form method="post" action="{{ route('cp.family-loans.reverse', $loan) }}" onsubmit="return confirm('إلغاء هذه الحركة؟')">
                            @csrf
                            <button class="text-rose-600 text-xs">إلغاء</button>
                        </form>
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
