@extends('cp.layout')

@section('title', 'المستخدمون')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white">مستخدمو لوحة التحكم</h2>
        <a href="{{ route('cp.users.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white font-medium text-sm">
            <span class="material-symbols-outlined text-lg">add</span>
            إضافة مستخدم
        </a>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        @if($users->isEmpty())
            <div class="p-12 text-center text-slate-500 dark:text-slate-400">لا يوجد مستخدمون.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">الاسم</th>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">البريد</th>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">الدور</th>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">الحالة</th>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300 w-28">إجراء</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($users as $u)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-800 dark:text-white">{{ $u->name }}</span>
                                    @if($u->is_super_admin)
                                        <span class="mr-2 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-700 dark:text-amber-400">مدير نظام</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $u->email }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $u->role?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $u->is_active ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-400' : 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300' }}">
                                        {{ $u->is_active ? 'نشط' : 'معطّل' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 flex gap-1">
                                    <a href="{{ route('cp.users.edit', $u) }}" class="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600" title="تعديل"><span class="material-symbols-outlined text-lg">edit</span></a>
                                    @if($u->id !== auth()->id())
                                        <form action="{{ route('cp.users.destroy', $u) }}" method="post" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 text-red-600" title="حذف"><span class="material-symbols-outlined text-lg">delete</span></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">{{ $users->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
