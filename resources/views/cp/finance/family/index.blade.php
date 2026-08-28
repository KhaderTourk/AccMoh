@extends('cp.layout')
@section('title', 'الأفراد')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between gap-3 flex-wrap">
        <form class="flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="بحث" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">بحث</button></form>
        <a href="{{ route('cp.family-members.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">إضافة فرد</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">الاسم</th><th class="px-3 py-2">القرابة</th><th class="px-3 py-2">دائن</th><th class="px-3 py-2">مدين</th><th class="px-3 py-2"></th></tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($members as $m)
                <tr>
                    <td class="px-3 py-2"><a href="{{ route('cp.family-members.show', $m) }}" class="text-primary font-medium">{{ $m->name }}</a></td>
                    <td class="px-3 py-2">{{ $m->relationship ?: '—' }}</td>
                    <td class="px-3 py-2">@foreach($currencies as $c) @php $v=$m->iOweAmount($c->id); @endphp @if(\App\Support\Money::isPositive($v))<div>{{ $c->format($v) }}</div>@endif @endforeach</td>
                    <td class="px-3 py-2">@foreach($currencies as $c) @php $v=$m->theyOweAmount($c->id); @endphp @if(\App\Support\Money::isPositive($v))<div>{{ $c->format($v) }}</div>@endif @endforeach</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('cp.family-members.show', $m) }}" class="p-1" title="عرض"><span class="material-symbols-outlined text-base">visibility</span></a>
                            <a href="{{ route('cp.family-members.edit', $m) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                            <form method="post" action="{{ route('cp.family-members.destroy', $m) }}" onsubmit="return confirm('حذف/أرشفة هذا الفرد؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-8 text-center text-slate-500">لا يوجد أفراد.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $members->links() }}</div>
    </div>
</div>
@endsection
