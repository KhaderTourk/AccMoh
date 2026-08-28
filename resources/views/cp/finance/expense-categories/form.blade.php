@extends('cp.layout')
@section('title', $category->exists ? 'تعديل تصنيف' : 'تصنيف جديد')
@section('content')
<form method="post" action="{{ $category->exists ? route('cp.expense-categories.update', [$scope, $category]) : route('cp.expense-categories.store', $scope) }}" class="max-w-xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    @if($category->exists) @method('PUT') @endif
    <p class="text-sm text-slate-500">{{ $title }}</p>
    <div><label class="text-sm">الاسم *</label><input name="name" value="{{ old('name', $category->name) }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <div><label class="text-sm">الترتيب</label><input type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    <label class="inline-flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))> نشط</label>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
</form>
@endsection
