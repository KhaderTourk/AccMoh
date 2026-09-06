@extends('cp.layout')
@section('title', $vendor->exists ? 'تعديل '.$type->label() : $type->label().' جديد')
@section('content')
<form method="post" action="{{ $vendor->exists ? route('cp.'.$type->routePrefix().'.update', $vendor) : route('cp.'.$type->routePrefix().'.store') }}" class="max-w-xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    @if($vendor->exists) @method('PUT') @endif
    <div><label class="text-sm">الاسم *</label><input name="name" value="{{ old('name', $vendor->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div>
        <label class="text-sm">الهاتف</label>
        <input name="phone" value="{{ old('phone', $vendor->phone) }}" placeholder="05xxxxxxxx" inputmode="numeric" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>
    @if($type === \App\Enums\VendorType::Worker)
        <div><label class="text-sm">المسمى الوظيفي</label><input name="job_title" value="{{ old('job_title', $vendor->job_title) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    @else
        <div><label class="text-sm">وصف العمل</label><input name="work_description" value="{{ old('work_description', $vendor->work_description) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    @endif
    <div><label class="text-sm">ملاحظات</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $vendor->notes) }}</textarea></div>
    <div class="flex flex-wrap gap-2">
        <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
        <a href="{{ route('cp.'.$type->routePrefix().'.index') }}" class="px-5 py-2 rounded-xl border">إلغاء</a>
    </div>
</form>
@if($vendor->exists)
    <form method="post" action="{{ route('cp.'.$type->routePrefix().'.destroy', $vendor) }}" class="max-w-xl mt-3" onsubmit="return confirm('حذف/أرشفة {{ $type->label() }}؟')">
        @csrf @method('DELETE')
        <button type="submit" class="px-5 py-2 rounded-xl border border-rose-200 text-rose-600">حذف</button>
    </form>
@endif
@endsection
