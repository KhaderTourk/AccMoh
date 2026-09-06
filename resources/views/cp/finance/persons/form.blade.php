@extends('cp.layout')
@section('title', $member->exists ? 'تعديل شخص' : 'شخص جديد')
@section('content')
<form method="post" action="{{ $member->exists ? route('cp.persons.update', $member) : route('cp.persons.store') }}" class="max-w-xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    @if($member->exists) @method('PUT') @endif
    <div><label class="text-sm">الاسم *</label><input name="name" value="{{ old('name', $member->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm">القرابة</label><input name="relationship" value="{{ old('relationship', $member->relationship) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div>
            <label class="text-sm">الهاتف</label>
            <input name="phone" value="{{ old('phone', $member->phone) }}" placeholder="05xxxxxxxx" inputmode="numeric" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
    </div>
    <div><label class="text-sm">ملاحظات</label><textarea name="notes" rows="3" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $member->notes) }}</textarea></div>
    <div class="flex gap-2">
        <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
        <a href="{{ route('cp.persons.index') }}" class="px-5 py-2 rounded-xl border">إلغاء</a>
    </div>
</form>
@endsection
