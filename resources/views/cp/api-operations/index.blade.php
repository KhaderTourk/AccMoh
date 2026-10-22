@extends('cp.layout')
@section('title', 'عمليات الـ API')
@section('content')
<div class="space-y-4">
    <div class="cp-toolbar">
        <form class="flex flex-wrap gap-2 items-center">
            <select name="status" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">كل العمليات</option>
                <option value="pending" @selected(request('status') === 'pending')>القائمة (لم يُتراجع عنها)</option>
                <option value="undone" @selected(request('status') === 'undone')>تم التراجع عنها</option>
            </select>
            <select name="user_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">كل المستخدمين</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $from }}" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <input type="date" name="to" value="{{ $to }}" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <button class="cp-btn cp-btn-muted">تصفية</button>
        </form>
        <span class="text-sm text-slate-500">كل إضافة أو تعديل يتم عبر الـ API يُسجَّل هنا ويمكن التراجع عنه.</span>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">#</th>
                <th class="px-3 py-2">العملية</th>
                <th class="px-3 py-2">المستخدم / الجهاز</th>
                <th class="px-3 py-2">الوقت</th>
                <th class="px-3 py-2">التغييرات</th>
                <th class="px-3 py-2">الحالة</th>
                <th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($operations as $op)
                @php($counts = $op->changeCounts())
                <tr class="{{ $op->isUndone() ? 'opacity-50' : '' }}">
                    <td class="px-3 py-2 text-slate-500">{{ $op->id }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-flex items-center rounded-md bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 px-1.5 py-0.5 text-[10px] font-bold ml-1">API</span>
                        <a href="{{ route('cp.api-operations.show', $op) }}" class="font-medium hover:text-primary">{{ $op->label() }}</a>
                        <div class="text-xs text-slate-400 font-mono" dir="ltr">{{ $op->method }} {{ $op->path }}</div>
                    </td>
                    <td class="px-3 py-2">
                        {{ $op->user?->name ?? '—' }}
                        @if($op->token_name)<div class="text-xs text-slate-500">{{ $op->token_name }}</div>@endif
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ format_date($op->created_at, true) }}</td>
                    <td class="px-3 py-2 text-xs">
                        @if($counts['created'])<span class="text-emerald-600">+{{ $counts['created'] }} جديد</span>@endif
                        @if($counts['updated'])<span class="text-amber-600">{{ $counts['updated'] }} معدّل</span>@endif
                        @if($counts['deleted'])<span class="text-rose-600">{{ $counts['deleted'] }} محذوف</span>@endif
                    </td>
                    <td class="px-3 py-2 text-xs">
                        @if($op->isUndone())
                            <span class="text-slate-500">تم التراجع {{ format_date($op->undone_at, true) }}@if($op->undoer) — {{ $op->undoer->name }}@endif</span>
                        @else
                            <span class="text-emerald-600">قائمة</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <a href="{{ route('cp.api-operations.show', $op) }}" class="text-xs text-primary">التفاصيل</a>
                        @unless($op->isUndone())
                            <form method="post" action="{{ route('cp.api-operations.undo', $op) }}" class="inline" onsubmit="return confirm('التراجع عن هذه العملية وإرجاع البيانات كما كانت قبلها؟')">
                                @csrf
                                <button class="text-rose-600 text-xs mr-2">تراجع</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-8 text-center text-slate-500">لا توجد عمليات عبر الـ API.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $operations->links() }}</div>
    </div>
</div>
@endsection
