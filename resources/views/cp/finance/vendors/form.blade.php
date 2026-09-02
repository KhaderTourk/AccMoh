@extends('cp.layout')
@section('title', $vendor->exists ? 'تعديل '.$type->label() : $type->label().' جديد')
@section('content')
<form method="post" action="{{ $vendor->exists ? route('cp.'.$type->routePrefix().'.update', $vendor) : route('cp.'.$type->routePrefix().'.store') }}" class="max-w-xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    @if($vendor->exists) @method('PUT') @endif
    <div><label class="text-sm">الاسم *</label><input name="name" value="{{ old('name', $vendor->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div><label class="text-sm">الهاتف</label><input name="phone" value="{{ old('phone', $vendor->phone) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div><label class="text-sm">ملاحظات</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $vendor->notes) }}</textarea></div>
    <label class="inline-flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vendor->is_active ?? true))> نشط</label>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
</form>
@endsection
