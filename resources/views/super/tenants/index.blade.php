@extends('super.layout')
@section('title', 'النسخ')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <h1 class="text-xl font-extrabold">نسخ النظام</h1>
        <a href="{{ route('super.tenants.create') }}" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm">إضافة نسخة</a>
    </div>
    <div class="rounded-2xl bg-white border overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50"><tr>
                <th class="px-3 py-2">الاسم</th><th class="px-3 py-2">المالك</th><th class="px-3 py-2">العمل</th><th class="px-3 py-2">الحالة</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y">
            @forelse($tenants as $t)
                <tr>
                    <td class="px-3 py-2 font-medium">{{ $t->name }}<div class="text-xs text-slate-400">{{ $t->slug }}</div></td>
                    <td class="px-3 py-2">{{ $t->owner?->email ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $t->business_enabled ? 'مفعّل' : 'مخفي' }}</td>
                    <td class="px-3 py-2">{{ $t->is_active ? 'نشط' : 'موقوف' }}</td>
                    <td class="px-3 py-2">
                        <a class="text-emerald-700" href="{{ route('super.tenants.show', $t) }}">إدارة</a>
                        <a class="text-slate-700 mr-2" href="{{ route('super.tenants.finances', $t) }}">أرصدة</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-8 text-center text-slate-500">لا توجد نسخ بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $tenants->links() }}</div>
    </div>
</div>
@endsection
