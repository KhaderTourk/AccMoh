@extends('cp.layout')
@section('title', 'الخدمات')
@section('content')
<div class="space-y-4">
    <div class="cp-toolbar">
        <p class="text-sm text-slate-500">كتالوج الخدمات التي تقدّمها للزبائن.</p>
        <a href="{{ route('cp.service-types.create') }}" class="cp-btn cp-btn-primary"><span class="material-symbols-outlined">add</span> خدمة جديدة</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">الاسم</th><th class="px-3 py-2">الوصف</th><th class="px-3 py-2">الحالة</th><th class="px-3 py-2"></th></tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($types as $t)
                <tr class="{{ $t->is_active ? '' : 'opacity-40' }}">
                    <td class="px-3 py-2">{{ $t->name }}</td>
                    <td class="px-3 py-2">{{ $t->description ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $t->is_active ? 'نشط' : 'معطّل' }}</td>
                    <td class="px-3 py-2">
                        <div class="flex gap-2 justify-end">
                            <a href="{{ route('cp.service-types.edit', $t) }}" class="text-primary text-sm">تعديل</a>
                            <form method="post" action="{{ route('cp.service-types.destroy', $t) }}" onsubmit="return confirm('حذف الخدمة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-rose-600 text-sm">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-8 text-center text-slate-500">لا توجد خدمات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $types->links() }}</div>
    </div>
</div>
@endsection
