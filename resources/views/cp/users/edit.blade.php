@extends('cp.layout')

@section('title', 'تعديل مستخدم')

@section('content')
<div class="w-full max-w-xl space-y-6">
    <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        <a href="{{ route('cp.users.index') }}" class="hover:text-primary">المستخدمون</a>
        <span class="material-symbols-outlined text-lg">chevron_left</span>
        <span>تعديل {{ $user->name }}</span>
    </div>

    <form action="{{ route('cp.users.update', $user) }}" method="post" class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="cp-label block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">الاسم *</label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="cp-input w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5">
            @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="cp-label block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">البريد الإلكتروني *</label>
            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="cp-input w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5">
            @error('email')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="cp-label block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">كلمة المرور الجديدة (اتركها فارغة للإبقاء على الحالية)</label>
            <input type="password" name="password" id="password" class="cp-input w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5">
            @error('password')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="cp-label block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">تأكيد كلمة المرور</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="cp-input w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5">
        </div>
        <div>
            <label for="role_id" class="cp-label block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">الدور *</label>
            <select name="role_id" id="role_id" required class="cp-input w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5">
                @foreach($roles as $r)
                    <option value="{{ $r->id }}" {{ old('role_id', $user->role_id) == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        @if(auth()->user()->is_super_admin)
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_super_admin" id="is_super_admin" value="1" {{ old('is_super_admin', $user->is_super_admin) ? 'checked' : '' }} class="rounded border-slate-300 text-primary">
            <label for="is_super_admin" class="text-sm text-slate-700 dark:text-slate-300">مدير نظام</label>
        </div>
        @endif
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-primary">
            <label for="is_active" class="text-sm text-slate-700 dark:text-slate-300">نشط</label>
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('cp.users.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-medium">إلغاء</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-medium">حفظ</button>
        </div>
    </form>
</div>
@endsection
