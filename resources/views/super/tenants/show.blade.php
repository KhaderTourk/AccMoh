@extends('super.layout')
@section('title', $tenant->name)
@section('content')
<div class="space-y-4 max-w-4xl">
    <div class="flex justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-extrabold">{{ $tenant->name }}</h1>
            <p class="text-sm text-slate-500">{{ $tenant->slug }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('super.tenants.finances', $tenant) }}" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold">الأرصدة والحركات</a>
            <a href="{{ route('super.tenants.reports', $tenant) }}" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm font-bold">التقارير</a>
            <a href="{{ route('super.tenants.edit', $tenant) }}" class="px-4 py-2 rounded-xl border bg-white text-sm">تعديل</a>
        </div>
    </div>
    <div class="rounded-2xl bg-white border p-5 space-y-2 text-sm">
        <p>العمل: <strong>{{ $tenant->business_enabled ? 'ظاهر' : 'مخفي' }}</strong></p>
        <p>الحالة: <strong>{{ $tenant->is_active ? 'نشطة' : 'موقوفة' }}</strong></p>
        <p>المالك: <strong>{{ $tenant->owner?->name }}</strong> — {{ $tenant->owner?->email }}</p>
        @if($tenant->notes)
            <div>
                <p class="font-medium">ملاحظات</p>
                <p class="text-slate-600 whitespace-pre-line">{{ $tenant->notes }}</p>
            </div>
        @endif
        <p class="pt-3 text-slate-500">دخول المالك إلى نسخته عبر <code class="bg-slate-100 px-1 rounded">/cp/login</code></p>
    </div>
</div>
@endsection
