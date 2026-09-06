@extends('cp.layout')
@section('title', 'الأشخاص')
@section('content')
<div class="space-y-4">
    <div class="cp-toolbar">
        <form class="flex gap-2 flex-wrap">
            <input name="q" value="{{ request('q') }}" placeholder="بحث بالاسم أو الهاتف..." class="rounded-xl border px-3 py-2 dark:bg-slate-700 min-w-[14rem]">
            <button class="cp-btn cp-btn-muted">بحث</button>
        </form>
        <a href="{{ route('cp.persons.create') }}" class="cp-btn cp-btn-primary"><span class="material-symbols-outlined">add</span> إضافة شخص</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">الاسم</th><th class="px-3 py-2">القرابة</th><th class="px-3 py-2">الهاتف</th><th class="px-3 py-2">الصافي</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($members as $m)
                <tr>
                    <td class="px-3 py-2">
                        <a href="{{ route('cp.persons.show', $m) }}" class="text-primary font-medium">{{ $m->name }}</a>
                        @include('cp.partials.note-line', ['notes' => $m->notes])
                    </td>
                    <td class="px-3 py-2">{{ $m->relationship ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $m->phone ?: '—' }}</td>
                    <td class="px-3 py-2">
                        @foreach($currencies as $c)
                            @php $net = $m->netAmount($c->id); @endphp
                            @if(!\App\Support\Money::isZero($net))
                                <div class="{{ \App\Support\Money::isNegative($net) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $c->format($net) }}</div>
                            @endif
                        @endforeach
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('cp.persons.show', $m) }}" class="p-1" title="عرض"><span class="material-symbols-outlined text-base">visibility</span></a>
                            <a href="{{ route('cp.persons.edit', $m) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                            <form method="post" action="{{ route('cp.persons.destroy', $m) }}" onsubmit="return confirm('حذف/أرشفة هذا الشخص؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-8 text-center text-slate-500">لا يوجد أشخاص.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $members->links() }}</div>
    </div>
</div>
@endsection
