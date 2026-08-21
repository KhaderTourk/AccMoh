@extends('cp.layout')

@section('title', 'تعديل دور')

@section('content')
<div class="w-full max-w-2xl space-y-6">
    <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        <a href="{{ route('cp.roles.index') }}" class="hover:text-primary">الأدوار</a>
        <span class="material-symbols-outlined text-lg">chevron_left</span>
        <span>تعديل {{ $role->name }}</span>
    </div>

    <form action="{{ route('cp.roles.update', $role) }}" method="post" class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="cp-label block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">اسم الدور *</label>
            <input type="text" name="name" id="name" value="{{ old('name', $role->name) }}" required class="cp-input w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5">
            @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="slug" class="cp-label block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">المعرّف *</label>
            <input type="text" name="slug" id="slug" value="{{ old('slug', $role->slug) }}" required class="cp-input w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5">
            @error('slug')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="cp-label block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">الوصف</label>
            <textarea name="description" id="description" rows="2" class="cp-input w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5">{{ old('description', $role->description) }}</textarea>
        </div>
        @php $rolePermissionIds = $role->permissions->pluck('id')->toArray(); @endphp
        <div>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">الصلاحيات</p>
            <div class="space-y-3">
                @foreach($permissions as $module => $items)
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ $module }}</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach($items as $p)
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="permissions[]" value="{{ $p->id }}" {{ in_array($p->id, old('permissions', $rolePermissionIds)) ? 'checked' : '' }} class="rounded border-slate-300 text-primary">
                                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ $p->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('cp.roles.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-medium">إلغاء</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-medium">حفظ</button>
        </div>
    </form>
</div>
@endsection
