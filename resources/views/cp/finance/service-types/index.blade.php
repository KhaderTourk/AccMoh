@extends('cp.layout')
@section('title', 'أنواع الخدمات')
@section('content')
<div class="space-y-4">
    <div class="flex justify-end"><a href="{{ route('cp.service-types.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">نوع جديد</a></div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">الاسم</th><th class="px-3 py-2">السعر الافتراضي</th><th class="px-3 py-2">الحالة</th><th class="px-3 py-2"></th></tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @foreach($types as $t)
                <tr>
                    <td class="px-3 py-2">{{ $t->name }}</td>
                    <td class="px-3 py-2">{{ $t->default_price ? ($t->defaultCurrency?->format($t->default_price) ?? $t->default_price) : '—' }}</td>
                    <td class="px-3 py-2">{{ $t->is_active ? 'نشط' : 'معطّل' }}</td>
                    <td class="px-3 py-2"><a href="{{ route('cp.service-types.edit', $t) }}" class="text-primary">تعديل</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="p-3">{{ $types->links() }}</div>
    </div>
</div>
@endsection
