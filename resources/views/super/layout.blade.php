<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'المنصة') — AccMa Super</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:Tajawal,sans-serif}</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<header class="bg-slate-900 text-white">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <a href="{{ route('super.dashboard') }}" class="font-extrabold tracking-wide">AccMa Super</a>
            <nav class="hidden sm:flex gap-3 text-sm text-slate-300">
                <a href="{{ route('super.dashboard') }}" class="hover:text-white">لوحة التحكم</a>
                <a href="{{ route('super.tenants.index') }}" class="hover:text-white">النسخ</a>
            </nav>
        </div>
        <form method="post" action="{{ route('super.logout') }}">@csrf
            <button class="text-sm text-slate-300 hover:text-white">خروج</button>
        </form>
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-6">
    @if(session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl bg-rose-50 text-rose-800 border border-rose-200 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>
</body>
</html>
