@extends('cp.layout')
@section('title', $type->exists ? 'تعديل خدمة' : 'خدمة جديدة')
@section('content')
<div class="max-w-xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    <form method="post" action="{{ $type->exists ? route('cp.service-types.update', $type) : route('cp.service-types.store') }}" class="space-y-4">
        @csrf
        @if($type->exists) @method('PUT') @endif
        <div><label class="text-sm">الاسم *</label><input name="name" value="{{ old('name', $type->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">الوصف</label><textarea name="description" rows="3" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('description', $type->description) }}</textarea></div>
        <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
    </form>
    @if($type->exists)
        <form method="post" action="{{ route('cp.service-types.destroy', $type) }}" onsubmit="return confirm('حذف الخدمة؟')">
            @csrf @method('DELETE')
            <button type="submit" class="px-5 py-2 rounded-xl border border-rose-200 text-rose-600">حذف</button>
        </form>
    @endif
</div>
@endsection
