@extends('cp.layout')
@section('title', $client->exists ? 'تعديل عميل' : 'عميل جديد')
@section('content')
<form method="post" action="{{ $client->exists ? route('cp.clients.update', $client) : route('cp.clients.store') }}" class="max-w-2xl space-y-4 rounded-2xl bg-white dark:bg-slate-800 border p-6">
    @csrf
    @if($client->exists) @method('PUT') @endif
    <div><label class="text-sm">الجهة *</label><input name="name" value="{{ old('name', $client->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div><label class="text-sm">الشخص المسؤول</label><input name="contact_name" value="{{ old('contact_name', $client->contact_name) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div><label class="text-sm">الهاتف</label><input name="phone" value="{{ old('phone', $client->phone) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div><label class="text-sm">ملاحظات</label><textarea name="notes" rows="3" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $client->notes) }}</textarea></div>
    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active ?? true))> نشط</label>
    <div class="flex gap-2"><button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button><a href="{{ route('cp.clients.index') }}" class="px-5 py-2 rounded-xl border">إلغاء</a></div>
</form>
@endsection
