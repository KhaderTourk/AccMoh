@extends('cp.layout')
@section('title', $client->exists ? 'تعديل زبون' : 'زبون جديد')
@section('content')
<form method="post" action="{{ $client->exists ? route('cp.clients.update', $client) : route('cp.clients.store') }}" class="max-w-2xl space-y-4 rounded-2xl bg-white dark:bg-slate-800 border p-6">
    @csrf
    @if($client->exists) @method('PUT') @endif
    <div><label class="text-sm">الاسم *</label><input name="name" value="{{ old('name', $client->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div><label class="text-sm">الجهة</label><input name="company_name" value="{{ old('company_name', $client->company_name) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div>
        <label class="text-sm">الهاتف</label>
        <input name="phone" value="{{ old('phone', $client->phone) }}" placeholder="05xxxxxxxx" inputmode="numeric" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        <p class="text-xs text-slate-500 mt-1">10 خانات تبدأ بـ 05</p>
    </div>
    <div><label class="text-sm">ملاحظات</label><textarea name="notes" rows="3" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes', $client->notes) }}</textarea></div>
    <div class="flex gap-2"><button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button><a href="{{ route('cp.clients.index') }}" class="px-5 py-2 rounded-xl border">إلغاء</a></div>
</form>
@endsection
