@extends('cp.layout')
@section('title', $type->exists ? 'تعديل نوع خدمة' : 'نوع خدمة جديد')
@section('content')
<div class="max-w-xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    <form method="post" action="{{ $type->exists ? route('cp.service-types.update', $type) : route('cp.service-types.store') }}" class="space-y-4">
        @csrf
        @if($type->exists) @method('PUT') @endif
        <div><label class="text-sm">الاسم *</label><input name="name" value="{{ old('name', $type->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">الوصف</label><textarea name="description" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('description', $type->description) }}</textarea></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-sm">سعر افتراضي</label><input type="number" step="0.01" min="0" name="default_price" value="{{ old('default_price', $type->default_price) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
            <div><label class="text-sm">عملة افتراضية</label><select name="default_currency_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">—</option>@foreach($currencies as $c)<option value="{{ $c->id }}" @selected(old('default_currency_id', $type->default_currency_id)==$c->id)>{{ $c->code }}</option>@endforeach</select></div>
        </div>
        <label class="inline-flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active ?? true))> نشط</label>
        <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
    </form>
    @if($type->exists)
        <form method="post" action="{{ route('cp.service-types.destroy', $type) }}" onsubmit="return confirm('حذف نوع الخدمة؟')">
            @csrf @method('DELETE')
            <button type="submit" class="px-5 py-2 rounded-xl border border-rose-200 text-rose-600">حذف</button>
        </form>
    @endif
</div>
@endsection
