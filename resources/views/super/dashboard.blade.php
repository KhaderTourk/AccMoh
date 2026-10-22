@extends('super.layout')
@section('title', 'لوحة المنصة')
@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-end gap-3 flex-wrap">
        <div>
            <p class="text-sm text-slate-500">مرحباً بك في</p>
            <h1 class="text-2xl lg:text-3xl font-extrabold">لوحة مدير المنصة</h1>
        </div>
        <a href="{{ route('super.tenants.create') }}" class="cp-btn cp-btn-primary"><span class="material-symbols-outlined">add</span> نسخة جديدة</a>
    </div>
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="cp-count-card">
            <span class="cp-count-icon super-icon-violet"><span class="material-symbols-outlined">dns</span></span>
            <div><p class="cp-count-label">إجمالي النسخ</p><p class="cp-count-value" data-countup>{{ $tenantsCount }}</p></div>
        </div>
        <div class="cp-count-card">
            <span class="cp-count-icon"><span class="material-symbols-outlined">check_circle</span></span>
            <div><p class="cp-count-label">نسخ نشطة</p><p class="cp-count-value text-emerald-600" data-countup>{{ $activeTenants }}</p></div>
        </div>
        <div class="cp-count-card">
            <span class="cp-count-icon super-icon-sky"><span class="material-symbols-outlined">group</span></span>
            <div><p class="cp-count-label">مستخدمو النسخ</p><p class="cp-count-value" data-countup>{{ $usersCount }}</p></div>
        </div>
    </div>
    <a href="{{ route('super.tenants.index') }}" class="cp-panel flex items-center justify-between gap-3 rounded-2xl">
        <span class="flex items-center gap-3">
            <span class="cp-stat-icon"><span class="material-symbols-outlined">dns</span></span>
            <span><strong class="block">إدارة النسخ</strong><span class="text-sm text-slate-500">عرض النسخ وتعديلها وأرصدتها وتقاريرها</span></span>
        </span>
        <span class="material-symbols-outlined text-slate-400">arrow_back</span>
    </a>
</div>
@endsection
