@extends('cp.layout')
@section('title', 'الزبائن')
@section('content')
<div class="space-y-4">
    <div class="cp-toolbar">
        <form class="flex flex-wrap gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث بالاسم أو الهاتف..." class="rounded-xl border px-3 py-2 dark:bg-slate-700 min-w-[14rem]">
            <select name="status" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                <option value="active" @selected(request('status')==='active')>نشط</option>
                <option value="inactive" @selected(request('status')==='inactive')>غير نشط</option>
            </select>
            <button class="cp-btn cp-btn-muted">بحث</button>
        </form>
        <a href="{{ route('cp.clients.create') }}" class="cp-btn cp-btn-primary"><span class="material-symbols-outlined">add</span> زبون جديد</a>
    </div>
    <div class="rounded-2xl bg-white dark:bg-slate-800 border overflow-hidden">
        @if($clients->isEmpty())
            <div class="p-12 text-center text-slate-500">لا يوجد زبائن بعد.</div>
        @else
        <table class="w-full text-right text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-4 py-3">الاسم</th><th class="px-4 py-3">الجهة</th><th class="px-4 py-3">الهاتف</th><th class="px-4 py-3">المتبقي</th><th class="px-4 py-3">الحالة</th><th class="px-4 py-3">إجراء</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @foreach($clients as $client)
                <tr>
                    <td class="px-4 py-3">
                        <a href="{{ route('cp.clients.show', $client) }}" class="font-medium text-primary">{{ $client->name }}</a>
                        @include('cp.partials.note-line', ['notes' => $client->notes])
                    </td>
                    <td class="px-4 py-3">{{ $client->company_name ?: '—' }}</td>
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
                            <a href="{{ route('cp.clients.show', $client) }}" class="p-2 inline-block" title="عرض"><span class="material-symbols-outlined text-base">visibility</span></a>
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
        <div class="p-3">{{ $clients->links() }}</div>
        @endif
    </div>
</div>
@endsection
