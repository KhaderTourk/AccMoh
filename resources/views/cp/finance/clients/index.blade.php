@extends('cp.layout')
@section('title', 'الزبائن')
@section('content')
@php
    $filterCount = collect(['q', 'status', 'from', 'to', '_preset'])->filter(fn ($key) => filled(request($key)))->count();
    $periodQuery = $periodQuery ?? [];
@endphp
<div class="space-y-4">
    @component('cp.partials.filter-panel', ['count' => $filterCount])
        @slot('actions')
            <a href="{{ route('cp.clients.create') }}" class="cp-btn cp-btn-primary"><span class="material-symbols-outlined">add</span> زبون جديد</a>
        @endslot
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">بحث</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="الاسم أو الجهة أو الهاتف..." class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">الحالة</label>
            <select name="status" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                <option value="active" @selected(request('status')==='active')>نشط</option>
                <option value="inactive" @selected(request('status')==='inactive')>غير نشط</option>
            </select>
        </div>
        @include('cp.partials.date-range-fields')
        @slot('footer')
            @include('cp.partials.date-range-shortcuts')
            @if($from || $to)
                <p class="text-xs text-slate-500 mt-2">عند تحديد فترة تظهر فقط الزبائن الذين لديهم خدمات أو دفعات ضمنها، ويمكن تصدير كشف الحركات لنفس الفترة.</p>
            @endif
        @endslot
    @endcomponent

    @if($from || $to)
        <div class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm">
            حركات الفترة: <strong>{{ \App\Support\DateRange::label($from, $to) }}</strong>
            — التصدير من كل صف يعرض خدمات ودفعات هذه الفترة مع الرصيد السابق إن وُجد.
        </div>
    @endif

    <div class="rounded-2xl bg-white dark:bg-slate-800 border overflow-hidden">
        @if($clients->isEmpty())
            <div class="p-12 text-center text-slate-500">لا يوجد زبائن مطابقون للتصفية.</div>
        @else
        <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-4 py-3">الجهة</th><th class="px-4 py-3">الاسم</th><th class="px-4 py-3">الهاتف</th><th class="px-4 py-3">المتبقي</th><th class="px-4 py-3">الحالة</th><th class="px-4 py-3">إجراء</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @foreach($clients as $client)
                <tr>
                    <td class="px-4 py-3">
                        <a href="{{ route('cp.clients.show', array_merge(['client' => $client], $periodQuery)) }}" class="font-medium text-primary">{{ $client->organization() ?: '—' }}</a>
                        @include('cp.partials.note-line', ['notes' => $client->notes])
                    </td>
                    <td class="px-4 py-3">{{ $client->personName() }}</td>
                    <td class="px-4 py-3">{{ $client->phone ?: '—' }}</td>
                    <td class="px-4 py-3">
                        @foreach($currencies as $currency)
                            @php $due = $client->outstandingAmount($currency->id); @endphp
                            @if(\App\Support\Money::isPositive($due))
                                <div>{{ $currency->format($due) }}</div>
                            @elseif(\App\Support\Money::isNegative($due))
                                <div class="text-emerald-600">عربون {{ $currency->format(\App\Support\Money::abs($due)) }}</div>
                            @endif
                        @endforeach
                    </td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs {{ $client->is_active ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-600 dark:text-slate-300' }}">{{ $client->is_active ? 'نشط' : 'مؤرشف' }}</span></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('cp.clients.show', array_merge(['client' => $client], $periodQuery)) }}" class="p-2 inline-block" title="عرض"><span class="material-symbols-outlined text-base">visibility</span></a>
                            <a href="{{ route('cp.clients.export-pdf', array_merge(['client' => $client], $periodQuery)) }}" class="p-2 inline-block" title="تصدير كشف الفترة"><span class="material-symbols-outlined text-base">picture_as_pdf</span></a>
                            <a href="{{ route('cp.clients.edit', $client) }}" class="p-2 inline-block" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                            <form method="post" action="{{ route('cp.clients.destroy', $client) }}" onsubmit="return confirm('حذف/أرشفة هذا الزبون؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="p-3">{{ $clients->links() }}</div>
        @endif
    </div>
</div>
@endsection
