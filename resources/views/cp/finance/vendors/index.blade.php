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
                @foreach($currencies as $c)<th class="px-3 py-2">المتبقي {{ $c->code }}</th>@endforeach
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
                        @php $due = $v->outstandingAmount($c->id); @endphp
                        <td class="px-3 py-2">
                            @if(\App\Support\Money::isPositive($due))
                                {{ $c->format($due) }}
                            @elseif(\App\Support\Money::isNegative($due))
                                <span class="text-emerald-600">مقدماً {{ $c->format(\App\Support\Money::abs($due)) }}</span>
                            @else
                                —
                            @endif
                        </td>
                    @endforeach
                    <td class="px-3 py-2">{{ $v->is_active ? 'نشط' : 'مؤرشف' }}</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('cp.'.$type->routePrefix().'.edit', $v) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                            <form method="post" action="{{ route('cp.'.$type->routePrefix().'.destroy', $v) }}" onsubmit="return confirm('حذف/أرشفة {{ $type->label() }}؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                            </form>
                        </div>
                    </td>
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
