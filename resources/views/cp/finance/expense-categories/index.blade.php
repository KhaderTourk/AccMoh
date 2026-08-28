@extends('cp.layout')
@section('title', $title)
@section('content')
<div class="space-y-4">
    <div class="flex justify-end">
        <a href="{{ route('cp.expense-categories.create', $scope) }}" class="px-4 py-2 rounded-xl bg-primary text-white">تصنيف جديد</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">الاسم</th><th class="px-3 py-2">الترتيب</th><th class="px-3 py-2">الحالة</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($categories as $c)
                <tr>
                    <td class="px-3 py-2">{{ $c->name }}</td>
                    <td class="px-3 py-2">{{ $c->sort_order }}</td>
                    <td class="px-3 py-2">{{ $c->is_active ? 'نشط' : 'معطّل' }}</td>
                    <td class="px-3 py-2">
                        <div class="flex gap-2 justify-end">
                            <a href="{{ route('cp.expense-categories.edit', [$scope, $c]) }}" class="text-primary text-sm">تعديل</a>
                            <form method="post" action="{{ route('cp.expense-categories.destroy', [$scope, $c]) }}" onsubmit="return confirm('حذف التصنيف؟')">
                                @csrf @method('DELETE')
                                <button class="text-rose-600 text-sm">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-8 text-center text-slate-500">لا توجد تصنيفات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $categories->links() }}</div>
    </div>
</div>
@endsection
