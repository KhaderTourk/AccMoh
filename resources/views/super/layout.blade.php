<!DOCTYPE html>
<html lang="ar" dir="rtl" class="cp-admin super-admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1220">
    <title>@yield('title', 'المنصة') — حسابات براند · المنصة</title>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] },
                    colors: { primary: '#FB005B', 'primary-dark': '#d6004e' }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/cp.css') }}?v={{ @filemtime(public_path('assets/css/cp.css')) ?: time() }}">
</head>
<body class="font-sans text-slate-800 min-h-screen">
<div class="cp-main min-h-screen flex flex-col">
    <header class="super-header">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-5">
                <a href="{{ route('super.dashboard') }}" class="super-brand">
                    <span class="super-brand-mark"><span class="material-symbols-outlined">shield_person</span></span>
                    <span>حسابات براند <em>المنصة</em></span>
                </a>
                <nav class="super-nav">
                    <a href="{{ route('super.dashboard') }}" class="{{ request()->routeIs('super.dashboard') ? 'is-active' : '' }}">
                        <span class="material-symbols-outlined">space_dashboard</span><span>لوحة التحكم</span>
                    </a>
                    <a href="{{ route('super.tenants.index') }}" class="{{ request()->routeIs('super.tenants.*') ? 'is-active' : '' }}">
                        <span class="material-symbols-outlined">dns</span><span>النسخ</span>
                    </a>
                </nav>
            </div>
            <form method="post" action="{{ route('super.logout') }}">@csrf
                <button class="super-logout"><span class="material-symbols-outlined">logout</span><span class="hidden sm:inline">خروج</span></button>
            </form>
        </div>
    </header>
    <main class="max-w-6xl w-full mx-auto px-4 py-6 lg:py-8 flex-1">
        @if(session('success'))
            <div class="cp-flash cp-flash-success" role="status"><span>{{ session('success') }}</span></div>
        @endif
        @if(session('error'))
            <div class="cp-flash cp-flash-error" role="alert"><span>{{ session('error') }}</span></div>
        @endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('assets/js/cp-ui.js') }}?v={{ @filemtime(public_path('assets/js/cp-ui.js')) ?: time() }}"></script>
</body>
</html>
