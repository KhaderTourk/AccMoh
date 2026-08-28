@extends('super.layout')
@section('title', $tenant->exists ? 'تعديل نسخة' : 'نسخة جديدة')
@section('content')
<form method="post" action="{{ $tenant->exists ? route('super.tenants.update', $tenant) : route('super.tenants.store') }}" class="max-w-2xl rounded-2xl bg-white border p-6 space-y-4">
    @csrf
    @if($tenant->exists) @method('PUT') @endif
    <h1 class="text-xl font-extrabold">{{ $tenant->exists ? 'تعديل النسخة' : 'إنشاء نسخة AccMa' }}</h1>
    <div class="grid md:grid-cols-2 gap-3">
        <div class="md:col-span-2"><label class="text-sm">اسم النسخة *</label><input name="name" value="{{ old('name', $tenant->name) }}" required class="w-full rounded-xl border px-3 py-2"></div>
        <div><label class="text-sm">المعرّف (slug)</label><input name="slug" value="{{ old('slug', $tenant->slug) }}" class="w-full rounded-xl border px-3 py-2" placeholder="اختياري"></div>
        <div class="flex items-end gap-4 pb-2">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="business_enabled" value="1" @checked(old('business_enabled', $tenant->business_enabled ?? true))> تفعيل العمل</label>
            @if($tenant->exists)
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tenant->is_active))> نشطة</label>
            @endif
        </div>
    </div>
    <div><label class="text-sm">ملاحظات</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2">{{ old('notes', $tenant->notes) }}</textarea></div>

    <div class="rounded-xl border border-dashed p-4 space-y-3">
        <h2 class="font-bold text-sm">مستخدم النسخة (مالك)</h2>
        @if($tenant->exists)
            <p class="text-sm text-slate-500">البريد: <strong>{{ $tenant->owner?->email }}</strong> (لا يُغيّر من هنا)</p>
            <div><label class="text-sm">اسم المالك</label><input name="owner_name" value="{{ old('owner_name', $tenant->owner?->name) }}" class="w-full rounded-xl border px-3 py-2"></div>
            <div><label class="text-sm">كلمة مرور جديدة (اختياري)</label><input type="password" name="owner_password" class="w-full rounded-xl border px-3 py-2"></div>
        @else
            <div><label class="text-sm">اسم المالك *</label><input name="owner_name" value="{{ old('owner_name') }}" required class="w-full rounded-xl border px-3 py-2"></div>
            <div><label class="text-sm">بريد المالك *</label><input type="email" name="owner_email" value="{{ old('owner_email') }}" required class="w-full rounded-xl border px-3 py-2"></div>
            <div><label class="text-sm">كلمة المرور *</label><input type="password" name="owner_password" required class="w-full rounded-xl border px-3 py-2"></div>
        @endif
    </div>

    <button class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold">حفظ</button>
</form>
@endsection
