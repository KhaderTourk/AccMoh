@extends('cp.layout')

@section('title', 'الأدوار والصلاحيات')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white">الأدوار والصلاحيات</h2>
        <a href="{{ route('cp.roles.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white font-medium text-sm">
            <span class="material-symbols-outlined text-lg">add</span>
            إضافة دور
        </a>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        @if($roles->isEmpty())
            <div class="p-12 text-center text-slate-500 dark:text-slate-400">لا توجد أدوار.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">الدور</th>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">الوصف</th>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">عدد الصلاحيات</th>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">عدد المستخدمين</th>
                            <th class="px-4 py-3 text-sm font-medium text-slate-600 dark:text-slate-300 w-24">إجراء</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($roles as $r)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-white">{{ $r->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $r->description ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $r->permissions->count() }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $r->users_count }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('cp.roles.edit', $r) }}" class="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600" title="تعديل"><span class="material-symbols-outlined text-lg">edit</span></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
