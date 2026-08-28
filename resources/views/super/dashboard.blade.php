@extends('super.layout')
@section('title', 'لوحة المنصة')
@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center gap-3 flex-wrap">
        <h1 class="text-2xl font-extrabold">لوحة مدير المنصة</h1>
        <a href="{{ route('super.tenants.create') }}" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold">نسخة جديدة</a>
    </div>
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="rounded-2xl bg-white p-5 border"><p class="text-sm text-slate-500">إجمالي النسخ</p><p class="text-3xl font-extrabold">{{ $tenantsCount }}</p></div>
        <div class="rounded-2xl bg-white p-5 border"><p class="text-sm text-slate-500">نسخ نشطة</p><p class="text-3xl font-extrabold text-emerald-600">{{ $activeTenants }}</p></div>
        <div class="rounded-2xl bg-white p-5 border"><p class="text-sm text-slate-500">مستخدمو النسخ</p><p class="text-3xl font-extrabold">{{ $usersCount }}</p></div>
    </div>
</div>
@endsection
