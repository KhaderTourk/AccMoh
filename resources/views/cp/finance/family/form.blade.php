@extends('cp.layout')
@section('title', $member->exists ? 'تعديل فرد' : 'فرد جديد')
@section('content')
<form method="post" action="{{ $member->exists ? route('cp.family-members.update', $member) : route('cp.family-members.store') }}" class="max-w-xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    @if($member->exists) @method('PUT') @endif
    <div><label class="text-sm">الاسم *</label><input name="name" value="{{ old('name', $member->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm">صلة القرابة</label><input name="relationship" value="{{ old('relationship', $member->relationship) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">الهاتف</label><input name="phone" value="{{ old('phone', $member->phone) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    </div>
    <div><label class="text-sm">ملاحظات</label><textarea name="notes" rows="3" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $member->notes) }}</textarea></div>
    <label class="inline-flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $member->is_active ?? true))> نشط</label>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
</form>
@endsection
