@extends('cp.layout')
@section('title', $type->plural())
@section('content')
<div class="space-y-4">
    <div class="flex justify-between flex-wrap gap-3">
        <form class="flex flex-wrap gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
        </form>
        <a href="{{ route('cp.'.$type->routePrefix().'.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">إضافة</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">الاسم</th><th class="px-3 py-2">الهاتف</th>
                @foreach($currencies as $c)<th class="px-3 py-2">المدفوع {{ $c->code }}</th>@endforeach
                <th class="px-3 py-2">الحالة</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($vendors as $v)
                <tr>
                    <td class="px-3 py-2">
                        <a class="text-primary" href="{{ route('cp.'.$type->routePrefix().'.show', $v) }}">{{ $v->name }}</a>
                        @include('cp.partials.note-line', ['notes' => $v->notes])
                    </td>
                    <td class="px-3 py-2">{{ $v->phone ?: '—' }}</td>
                    @foreach($currencies as $c)
                        <td class="px-3 py-2">{{ $c->format($v->paidAmount($c->id)) }}</td>
                    @endforeach
                    <td class="px-3 py-2">{{ $v->is_active ? 'نشط' : 'مؤرشف' }}</td>
                    <td class="px-3 py-2"><a href="{{ route('cp.'.$type->routePrefix().'.edit', $v) }}" class="text-primary text-sm">تعديل</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ 4 + $currencies->count() }}" class="p-8 text-center text-slate-500">لا توجد سجلات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $vendors->links() }}</div>
    </div>
</div>
@endsection
