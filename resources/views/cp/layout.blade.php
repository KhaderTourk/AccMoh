<!DOCTYPE html>
<html class="cp-admin" dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#08A46D">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>@yield('title', 'لوحة التحكم') — AccMa</title>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    {{-- Non-blocking fonts: don't stall first paint on slow networks --}}
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    </noscript>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] },
                    colors: {
                        primary: '#08A46D',
                        'primary-dark': '#069660',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/cp.css') }}">
    @stack('styles')
</head>
<body class="font-sans bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen transition-colors duration-300">
    <div class="cp-wrap flex min-h-screen">
        <aside id="cp-sidebar" class="cp-sidebar fixed top-0 right-0 z-40 h-full w-64 bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 shadow-lg transform transition-transform duration-300 ease-out translate-x-full lg:translate-x-0 lg:static lg:shadow-none" aria-label="القائمة الجانبية">
            <div class="flex flex-col h-full">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <a href="{{ route('cp.dashboard') }}" class="flex items-center gap-2 font-bold text-slate-800 dark:text-white">
                        <span class="material-symbols-outlined text-primary">dashboard</span>
                        <span>لوحة التحكم</span>
                    </a>
                    <button type="button" id="cp-sidebar-close" class="lg:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="إغلاق القائمة">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <nav class="flex-1 overflow-y-auto py-4 px-3">
                    <ul class="space-y-0.5">
                        <li>
                            <a href="{{ route('cp.dashboard') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary dark:hover:text-primary transition-colors {{ request()->routeIs('cp.dashboard') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}">
                                <span class="material-symbols-outlined text-xl">dashboard</span>
                                <span>لوحة التحكم</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('cp.offline') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.offline') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}">
                                <span class="material-symbols-outlined text-xl">cloud_off</span>
                                <span>وضع عدم الاتصال</span>
                                <span id="cp-offline-badge" class="mr-auto hidden text-[10px] px-1.5 py-0.5 rounded-full bg-amber-500 text-white">0</span>
                            </a>
                        </li>
                        @if(cpCan('finance'))
                        <li>
                            <a href="{{ route('cp.balances.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.balances.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}">
                                <span class="material-symbols-outlined text-xl">account_balance_wallet</span>
                                <span>الصناديق والأرصدة</span>
                            </a>
                        </li>
                        @if(tenantBusinessEnabled())
                        <li class="cp-nav-section pt-2 mt-2 {{ request()->routeIs('cp.clients.*', 'cp.client-services.*', 'cp.payments.*', 'cp.service-types.*') ? '' : 'cp-collapsed' }}" data-section="business">
                            <button type="button" class="cp-section-toggle" aria-expanded="{{ request()->routeIs('cp.clients.*', 'cp.client-services.*', 'cp.payments.*', 'cp.service-types.*') ? 'true' : 'false' }}">
                                <span>العمل</span>
                                <span class="material-symbols-outlined cp-chevron">expand_more</span>
                            </button>
                            <ul class="cp-section-content space-y-0.5">
                                <li><a href="{{ route('cp.clients.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.clients.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">groups</span><span>العملاء</span></a></li>
                                <li><a href="{{ route('cp.client-services.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.client-services.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">work</span><span>الخدمات</span></a></li>
                                <li><a href="{{ route('cp.payments.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.payments.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">payments</span><span>دفعات العملاء</span></a></li>
                                <li><a href="{{ route('cp.service-types.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.service-types.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">category</span><span>أنواع الخدمات</span></a></li>
                            </ul>
                        </li>
                        @endif
                        <li class="cp-nav-section pt-2 mt-2 {{ request()->routeIs('cp.family-members.*', 'cp.family-loans.*') ? '' : 'cp-collapsed' }}" data-section="family">
                            <button type="button" class="cp-section-toggle" aria-expanded="{{ request()->routeIs('cp.family-members.*', 'cp.family-loans.*') ? 'true' : 'false' }}">
                                <span>العائلة</span>
                                <span class="material-symbols-outlined cp-chevron">expand_more</span>
                            </button>
                            <ul class="cp-section-content space-y-0.5">
                                <li><a href="{{ route('cp.family-members.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.family-members.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">family_restroom</span><span>الأفراد</span></a></li>
                                <li><a href="{{ route('cp.family-loans.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.family-loans.index') || request()->routeIs('cp.family-loans.create') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">handshake</span><span>مدين</span></a></li>
                                <li><a href="{{ route('cp.family-loans.repay') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.family-loans.repay*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">replay</span><span>دائن</span></a></li>
                            </ul>
                        </li>
                        <li class="cp-nav-section pt-2 mt-2 {{ request()->routeIs('cp.expenses.*', 'cp.transfers.*', 'cp.ledger.*', 'cp.reports.*') ? '' : 'cp-collapsed' }}" data-section="ops">
                            <button type="button" class="cp-section-toggle" aria-expanded="{{ request()->routeIs('cp.expenses.*', 'cp.transfers.*', 'cp.ledger.*', 'cp.reports.*') ? 'true' : 'false' }}">
                                <span>العمليات والتقارير</span>
                                <span class="material-symbols-outlined cp-chevron">expand_more</span>
                            </button>
                            <ul class="cp-section-content space-y-0.5">
                                <li><a href="{{ route('cp.expenses.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.expenses.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">receipt_long</span><span>المصروفات</span></a></li>
                                <li><a href="{{ route('cp.transfers.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.transfers.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">swap_horiz</span><span>التحويلات</span></a></li>
                                <li><a href="{{ route('cp.ledger.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.ledger.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">menu_book</span><span>دفتر الحركات</span></a></li>
                                <li><a href="{{ route('cp.reports.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.reports.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">analytics</span><span>التقارير</span></a></li>
                            </ul>
                        </li>
                        @endif
                        @if(cpCan('users'))
                        <li class="cp-nav-section pt-2 mt-2 {{ request()->routeIs('cp.users.*', 'cp.roles.*') ? '' : 'cp-collapsed' }}" data-section="system">
                            <button type="button" class="cp-section-toggle" aria-expanded="{{ request()->routeIs('cp.users.*', 'cp.roles.*') ? 'true' : 'false' }}">
                                <span>النظام</span>
                                <span class="material-symbols-outlined cp-chevron">expand_more</span>
                            </button>
                            <ul class="cp-section-content space-y-0.5">
                                <li>
                                    <a href="{{ route('cp.users.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.users.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}">
                                        <span class="material-symbols-outlined text-xl">people</span>
                                        <span>المستخدمون</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('cp.roles.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.roles.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}">
                                        <span class="material-symbols-outlined text-xl">admin_panel_settings</span>
                                        <span>الأدوار والصلاحيات</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        @endif
                    </ul>
                </nav>
                <div class="p-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-400 px-4">
                    AccMa — إدارة مالية شخصية
                </div>
            </div>
        </aside>

        <div class="cp-main flex-1 flex flex-col min-w-0">
            <header class="cp-header sticky top-0 z-20 flex items-center justify-between gap-4 px-4 py-3 bg-white/80 dark:bg-slate-800/80 backdrop-blur border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <button type="button" id="cp-sidebar-open" class="lg:hidden p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="فتح القائمة">
                        <span class="material-symbols-outlined text-2xl">menu</span>
                    </button>
                    <h1 class="text-lg font-bold text-slate-800 dark:text-white truncate">@yield('title', 'لوحة التحكم')</h1>
                </div>
                <div class="flex items-center gap-2">
                    <span id="cp-net-status" class="hidden sm:inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500">
                        <span class="material-symbols-outlined text-sm">wifi</span>
                        <span>متصل</span>
                    </span>
                    <span class="text-sm text-slate-500 dark:text-slate-400 hidden sm:inline">{{ auth()->user()->name ?? '' }}</span>
                    <form action="{{ route('cp.logout') }}" method="post" class="inline">
                        @csrf
                        <button type="submit" class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="تسجيل الخروج" aria-label="تسجيل الخروج">
                            <span class="material-symbols-outlined text-xl">logout</span>
                        </button>
                    </form>
                    <button type="button" id="cp-theme-toggle" class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" aria-label="تبديل الوضع الليلي">
                        <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                        <span class="material-symbols-outlined hidden dark:inline text-amber-400">light_mode</span>
                    </button>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-6">
                @if(session('success'))
                    <div class="mb-4 px-4 py-3 rounded-xl bg-primary/10 dark:bg-primary/20 text-primary border border-primary/20" role="alert">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20" role="alert">
                        {{ session('error') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20" role="alert">
                        <ul class="list-disc list-inside text-sm space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function() {
            const sidebar = document.getElementById('cp-sidebar');
            const openBtn = document.getElementById('cp-sidebar-open');
            const closeBtn = document.getElementById('cp-sidebar-close');
            const themeToggle = document.getElementById('cp-theme-toggle');

            function openSidebar() {
                sidebar.classList.remove('translate-x-full');
                sidebar.classList.add('translate-x-0');
                document.body.classList.add('overflow-hidden');
            }
            function closeSidebar() {
                if (window.innerWidth < 1024) {
                    sidebar.classList.add('translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                }
                document.body.classList.remove('overflow-hidden');
            }

            openBtn?.addEventListener('click', openSidebar);
            closeBtn?.addEventListener('click', closeSidebar);

            themeToggle?.addEventListener('click', function() {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('cp-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            });

            if (localStorage.getItem('cp-theme') === 'dark') document.documentElement.classList.add('dark');
            else if (localStorage.getItem('cp-theme') === 'light') document.documentElement.classList.remove('dark');

            if (window.innerWidth < 1024) { sidebar?.classList.add('translate-x-full'); sidebar?.classList.remove('translate-x-0'); }

            const STORAGE_KEY = 'cp-nav-sections';
            document.querySelectorAll('.cp-section-toggle').forEach(function(btn) {
                var section = btn.closest('.cp-nav-section');
                if (!section) return;
                var id = section.getAttribute('data-section');
                var saved = null;
                try { saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) {}
                if (saved && typeof saved[id] === 'boolean') {
                    if (saved[id]) {
                        section.classList.remove('cp-collapsed');
                        btn.setAttribute('aria-expanded', 'true');
                    } else {
                        section.classList.add('cp-collapsed');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                }
                btn.addEventListener('click', function() {
                    var collapsed = section.classList.toggle('cp-collapsed');
                    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    try {
                        var o = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
                        o[id] = !collapsed;
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(o));
                    } catch(e) {}
                });
            });
        })();

        // Offline status + Service Worker
        (function() {
            const net = document.getElementById('cp-net-status');
            function paintNet() {
                if (!net) return;
                const online = navigator.onLine;
                net.classList.remove('hidden');
                net.innerHTML = online
                    ? '<span class="material-symbols-outlined text-sm text-emerald-600">wifi</span><span>متصل</span>'
                    : '<span class="material-symbols-outlined text-sm text-amber-600">wifi_off</span><span>بدون نت</span>';
            }
            window.addEventListener('online', paintNet);
            window.addEventListener('offline', paintNet);
            paintNet();

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js').catch(function() {});
            }

            // Pending outbox badge (best-effort) — must use same DB version + upgrade as cp-offline.js
            const badge = document.getElementById('cp-offline-badge');
            if (badge && window.indexedDB) {
                try {
                    const req = indexedDB.open('accma_offline', 2);
                    req.onupgradeneeded = function() {
                        const db = req.result;
                        if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta');
                        if (!db.objectStoreNames.contains('snapshot')) db.createObjectStore('snapshot');
                        if (!db.objectStoreNames.contains('outbox')) {
                            const store = db.createObjectStore('outbox', { keyPath: 'operation_id' });
                            store.createIndex('by_status', 'status', { unique: false });
                            store.createIndex('by_created', 'created_at', { unique: false });
                        }
                    };
                    req.onsuccess = function() {
                        const db = req.result;
                        if (!db.objectStoreNames.contains('outbox')) return;
                        const tx = db.transaction('outbox', 'readonly');
                        const getAll = tx.objectStore('outbox').getAll();
                        getAll.onsuccess = function() {
                            const n = (getAll.result || []).filter(function(i) {
                                return i.status === 'pending' || i.status === 'failed';
                            }).length;
                            if (n > 0) {
                                badge.textContent = String(n);
                                badge.classList.remove('hidden');
                            }
                        };
                    };
                } catch (e) {}
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
