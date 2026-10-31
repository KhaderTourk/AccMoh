<!DOCTYPE html>
<html class="cp-admin" dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FB005B">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>@yield('title', 'لوحة التحكم') — حسابات براند</title>
    <script>
        // الثيم يُطبق قبل الرسم حتى لا يظهر وميض أبيض في الوضع الليلي
        (function () {
            try {
                var t = localStorage.getItem('cp-theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    {{-- Non-blocking fonts: don't stall first paint on slow networks --}}
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">
    </noscript>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] },
                    colors: {
                        primary: '#FB005B',
                        'primary-dark': '#d6004e',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/cp.css') }}?v={{ @filemtime(public_path('assets/css/cp.css')) ?: time() }}">
    @stack('styles')
</head>
<body class="font-sans bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen transition-colors duration-300">
    <div class="cp-wrap flex min-h-screen">
        <aside id="cp-sidebar" class="cp-sidebar fixed top-0 right-0 z-40 h-full w-64 bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 shadow-lg transform transition-transform duration-300 ease-out translate-x-full lg:translate-x-0 lg:static lg:shadow-none" aria-label="القائمة الجانبية">
            <div class="flex flex-col h-full">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <a href="{{ route('cp.dashboard') }}" class="cp-brand">
                        <span class="cp-brand-mark cp-brand-logo"><img src="{{ asset('assets/icons/brand-logo.png') }}" alt="براند" width="40" height="40"></span>
                        <span class="cp-brand-text">
                            <strong>حسابات براند</strong>
                            <span>الإدارة المالية</span>
                        </span>
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
                                <span>الأدراج والأرصدة</span>
                            </a>
                        </li>
                        @if(tenantBusinessEnabled())
                        <li class="cp-nav-section pt-2 mt-2 {{ request()->routeIs('cp.clients.*', 'cp.client-services.*', 'cp.service-types.*', 'cp.workers.*', 'cp.suppliers.*') ? '' : 'cp-collapsed' }}" data-section="business">
                            <button type="button" class="cp-section-toggle" aria-expanded="{{ request()->routeIs('cp.clients.*', 'cp.client-services.*', 'cp.service-types.*', 'cp.workers.*', 'cp.suppliers.*') ? 'true' : 'false' }}">
                                <span>العمل</span>
                                <span class="material-symbols-outlined cp-chevron">expand_more</span>
                            </button>
                            <ul class="cp-section-content space-y-0.5">
                                <li><a href="{{ route('cp.clients.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.clients.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">groups</span><span>الزبائن</span></a></li>
                                <li><a href="{{ route('cp.service-types.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.service-types.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">category</span><span>الخدمات</span></a></li>
                                <li><a href="{{ route('cp.workers.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.workers.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">badge</span><span>الموظفون</span></a></li>
                                <li><a href="{{ route('cp.suppliers.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.suppliers.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">local_shipping</span><span>الموردون</span></a></li>
                            </ul>
                        </li>
                        @endif
                        <li>
                            <a href="{{ route('cp.persons.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.persons.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}">
                                <span class="material-symbols-outlined text-xl">person</span>
                                <span>الأشخاص</span>
                            </a>
                        </li>
                        <li class="cp-nav-section pt-2 mt-2 {{ request()->routeIs('cp.payments.*', 'cp.transfers.*', 'cp.ledger.*', 'cp.reports.*', 'cp.api-operations.*') ? '' : 'cp-collapsed' }}" data-section="ops">
                            <button type="button" class="cp-section-toggle" aria-expanded="{{ request()->routeIs('cp.payments.*', 'cp.transfers.*', 'cp.ledger.*', 'cp.reports.*', 'cp.api-operations.*') ? 'true' : 'false' }}">
                                <span>العمليات والتقارير</span>
                                <span class="material-symbols-outlined cp-chevron">expand_more</span>
                            </button>
                            <ul class="cp-section-content space-y-0.5">
                                <li><a href="{{ route('cp.payments.incoming') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.payments.incoming') || (request()->routeIs('cp.payments.create') && request()->route('direction') === 'incoming') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">south_west</span><span>دفعات واردة</span></a></li>
                                <li><a href="{{ route('cp.payments.outgoing') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.payments.outgoing') || (request()->routeIs('cp.payments.create') && request()->route('direction') === 'outgoing') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">north_east</span><span>دفعات صادرة</span></a></li>
                                <li><a href="{{ route('cp.transfers.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.transfers.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">swap_horiz</span><span>التحويلات</span></a></li>
                                <li><a href="{{ route('cp.ledger.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.ledger.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">menu_book</span><span>سجل الحركات</span></a></li>
                                <li><a href="{{ route('cp.reports.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.reports.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">analytics</span><span>التقارير</span></a></li>
                                @php($apiPending = \App\Models\ApiOperation::query()->pending()->where('created_at', '>=', now()->subDay())->count())
                                <li><a href="{{ route('cp.api-operations.index') }}" class="cp-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors {{ request()->routeIs('cp.api-operations.*') ? 'bg-primary/10 text-primary dark:bg-primary/20' : '' }}"><span class="material-symbols-outlined text-xl">api</span><span>عمليات الـ API</span>@if($apiPending)<span class="mr-auto rounded-full bg-indigo-600 text-white text-[10px] px-1.5 py-0.5" title="عمليات آخر 24 ساعة">{{ $apiPending }}</span>@endif</a></li>
                            </ul>
                        </li>
                        @endif
                        @if(cpCan('users'))
                        <li class="cp-nav-section pt-2 mt-2 {{ request()->routeIs('cp.users.*') ? '' : 'cp-collapsed' }}" data-section="system">
                            <button type="button" class="cp-section-toggle" aria-expanded="{{ request()->routeIs('cp.users.*') ? 'true' : 'false' }}">
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
                            </ul>
                        </li>
                        @endif
                    </ul>
                </nav>
                <div class="p-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-400 px-4">
                    حسابات براند المالية
                </div>
            </div>
        </aside>
        <div id="cp-backdrop" class="cp-backdrop" aria-hidden="true"></div>

        <div class="cp-main flex-1 flex flex-col min-w-0">
            <header class="cp-header sticky top-0 z-20 flex items-center justify-between gap-4 px-4 py-3 bg-white/80 dark:bg-slate-800/80 backdrop-blur border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <button type="button" id="cp-sidebar-open" class="cp-icon-btn lg:hidden p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="فتح القائمة">
                        <span class="material-symbols-outlined text-2xl">menu</span>
                    </button>
                    <h1 class="text-lg font-bold text-slate-800 dark:text-white truncate">@yield('title', 'لوحة التحكم')</h1>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="cp-search-trigger" data-cp-palette title="بحث سريع (Ctrl+K)" aria-label="بحث سريع">
                        <span class="material-symbols-outlined text-xl">search</span>
                        <span class="hidden md:inline">بحث سريع</span>
                        <span class="cp-kbd hidden md:inline">Ctrl K</span>
                    </button>
                    <form action="{{ route('cp.archive-visibility') }}" method="post">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border text-sm font-medium transition-colors {{ \App\Support\ArchiveVisibility::showing() ? 'bg-amber-100 border-amber-300 text-amber-900 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200' : 'border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700' }}"
                                title="إظهار أو إخفاء الزبائن والمستخدمين والحركات والموظفين والموردين والأشخاص المؤرشفين">
                            <span class="material-symbols-outlined text-lg">{{ \App\Support\ArchiveVisibility::showing() ? 'visibility_off' : 'inventory_2' }}</span>
                            <span class="sm:hidden">المؤرشف</span>
                            <span class="hidden sm:inline">إظهار/إخفاء المؤرشف</span>
                        </button>
                    </form>
                    <span id="cp-net-status" class="hidden sm:inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500">
                        <span class="material-symbols-outlined text-sm">wifi</span>
                        <span>متصل</span>
                    </span>
                    @if(auth()->user())
                    <span class="cp-user-chip hidden sm:inline-flex">
                        <span class="cp-user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                        {{ auth()->user()->name }}
                    </span>
                    @endif
                    <form action="{{ route('cp.logout') }}" method="post" class="inline">
                        @csrf
                        <button type="submit" class="cp-icon-btn p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="تسجيل الخروج" aria-label="تسجيل الخروج">
                            <span class="material-symbols-outlined text-xl">logout</span>
                        </button>
                    </form>
                    <button type="button" id="cp-theme-toggle" class="cp-icon-btn p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" aria-label="تبديل الوضع الليلي">
                        <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                        <span class="material-symbols-outlined hidden dark:inline text-amber-400">light_mode</span>
                    </button>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-8">
                @if(session('success'))
                    <div class="cp-flash cp-flash-success cp-flash-toast" role="status" aria-live="polite" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>
                        <span>{{ session('success') }}</span>
                        <button type="button" class="p-0.5 rounded-lg hover:bg-black/5" @click="show = false" aria-label="إغلاق">
                            <span class="material-symbols-outlined text-base">close</span>
                        </button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="cp-flash cp-flash-error" role="alert" x-data="{ show: true }" x-show="show" x-transition>
                        <span>{{ session('error') }}</span>
                        <button type="button" class="p-0.5 rounded-lg hover:bg-black/5" @click="show = false" aria-label="إغلاق">
                            <span class="material-symbols-outlined text-base">close</span>
                        </button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="cp-flash cp-flash-error" role="alert">
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

    <?php
        $cpTabs = [];
        if (tenantBusinessEnabled()) {
            $cpTabs[] = ['route' => 'cp.clients.index', 'active' => 'cp.clients.*', 'icon' => 'groups', 'label' => 'الزبائن'];
            $cpTabs[] = ['route' => 'cp.workers.index', 'active' => 'cp.workers.*', 'icon' => 'badge', 'label' => 'الموظفون'];
            $cpTabs[] = ['route' => 'cp.suppliers.index', 'active' => 'cp.suppliers.*', 'icon' => 'storefront', 'label' => 'الموردون'];
        } else {
            $cpTabs[] = ['route' => 'cp.dashboard', 'active' => 'cp.dashboard', 'icon' => 'home', 'label' => 'الرئيسية'];
        }
        if (cpCan('finance')) {
            $cpTabs[] = ['route' => 'cp.persons.index', 'active' => 'cp.persons.*', 'icon' => 'person', 'label' => 'الأشخاص'];
        }
        $cpMore = [];
        if (tenantBusinessEnabled()) {
            $cpMore[] = ['url' => route('cp.dashboard'), 'icon' => 'home', 'label' => 'لوحة التحكم'];
        }
        if (cpCan('finance')) {
            $cpMore[] = ['url' => route('cp.balances.index'), 'icon' => 'account_balance_wallet', 'label' => 'الأدراج والأرصدة'];
            $cpMore[] = ['url' => route('cp.payments.incoming'), 'icon' => 'south_west', 'label' => 'دفعات واردة'];
            $cpMore[] = ['url' => route('cp.payments.outgoing'), 'icon' => 'north_east', 'label' => 'دفعات صادرة'];
            $cpMore[] = ['url' => route('cp.transfers.index'), 'icon' => 'swap_horiz', 'label' => 'التحويلات'];
            $cpMore[] = ['url' => route('cp.reports.index'), 'icon' => 'analytics', 'label' => 'التقارير'];
        }
        $cpMore[] = ['url' => route('cp.offline'), 'icon' => 'cloud_off', 'label' => 'وضع عدم الاتصال'];
    ?>
    <nav class="cp-bottom-nav cp-tabbar" aria-label="التنقل">
        @foreach($cpTabs as $tab)
            <a href="{{ route($tab['route']) }}" class="{{ request()->routeIs($tab['active']) ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">{{ $tab['icon'] }}</span><span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
        <button type="button" id="cp-more-open" aria-haspopup="dialog" aria-controls="cp-more-sheet">
            <span class="material-symbols-outlined">more_horiz</span><span>المزيد</span>
        </button>
    </nav>
    <div class="cp-more-sheet" id="cp-more-sheet" hidden>
        <div class="cp-more-panel" role="dialog" aria-label="المزيد">
            <span class="cp-more-grab"></span>
            @foreach($cpMore as $item)
                <a href="{{ $item['url'] }}"><span class="material-symbols-outlined">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>
            @endforeach
            <button type="button" id="cp-sidebar-open-bottom"><span class="material-symbols-outlined">menu</span>كل القائمة</button>
        </div>
    </div>

    <?php
        $cpQuickActions = [];
        if (cpCan('finance')) {
            $cpQuickActions[] = ['label' => 'دفعة واردة جديدة', 'icon' => 'south_west', 'url' => route('cp.payments.create', ['incoming'])];
            $cpQuickActions[] = ['label' => 'دفعة صادرة جديدة', 'icon' => 'north_east', 'url' => route('cp.payments.create', ['outgoing'])];
            $cpQuickActions[] = ['label' => 'تحويل جديد', 'icon' => 'swap_horiz', 'url' => route('cp.transfers.create')];
            $cpQuickActions[] = ['label' => 'شخص جديد', 'icon' => 'group_add', 'url' => route('cp.persons.create')];
            if (tenantBusinessEnabled()) {
                $cpQuickActions[] = ['label' => 'زبون جديد', 'icon' => 'person_add', 'url' => route('cp.clients.create')];
                $cpQuickActions[] = ['label' => 'خدمة زبون جديدة', 'icon' => 'work', 'url' => route('cp.client-services.create')];
            }
        }
    ?>
    <script type="application/json" id="cp-quick-actions">@json($cpQuickActions)</script>

    <script>
        (function() {
            const sidebar = document.getElementById('cp-sidebar');
            const openBtn = document.getElementById('cp-sidebar-open');
            const closeBtn = document.getElementById('cp-sidebar-close');
            const themeToggle = document.getElementById('cp-theme-toggle');

            const backdrop = document.getElementById('cp-backdrop');

            function openSidebar() {
                sidebar.classList.remove('translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop?.classList.add('is-open');
                document.body.classList.add('overflow-hidden');
            }
            function closeSidebar() {
                if (window.innerWidth < 1024) {
                    sidebar.classList.add('translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                }
                backdrop?.classList.remove('is-open');
                document.body.classList.remove('overflow-hidden');
            }

            openBtn?.addEventListener('click', openSidebar);
            closeBtn?.addEventListener('click', closeSidebar);
            backdrop?.addEventListener('click', closeSidebar);
            document.getElementById('cp-sidebar-open-bottom')?.addEventListener('click', function () { document.getElementById('cp-more-sheet').hidden = true; openSidebar(); });
            (function () {
                var sheet = document.getElementById('cp-more-sheet');
                var openBtn = document.getElementById('cp-more-open');
                if (!sheet || !openBtn) return;
                openBtn.addEventListener('click', function () { sheet.hidden = !sheet.hidden; openBtn.classList.toggle('is-active', !sheet.hidden); });
                sheet.addEventListener('click', function (e) { if (e.target === sheet) { sheet.hidden = true; openBtn.classList.remove('is-active'); } });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) { sheet.hidden = true; openBtn.classList.remove('is-active'); } });
            })();
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && backdrop?.classList.contains('is-open')) closeSidebar();
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024) backdrop?.classList.remove('is-open');
            });

            themeToggle?.addEventListener('click', function() {
                const apply = function () {
                    document.documentElement.classList.toggle('dark');
                    localStorage.setItem('cp-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                };
                if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) document.startViewTransition(apply);
                else apply();
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
    <script>
        (function () {
            var SEP = '\\';
            var MAX = [2, 2, 4];
            var MIN_YEAR = {{ \App\Support\DateRange::MIN_YEAR }};
            var MAX_YEAR = {{ \App\Support\DateRange::MAX_YEAR }};
            var INVALID = 'اختر تاريخاً صحيحاً من التقويم.';
            var SEP_RE = /[\\\/.\-\s]/;

            function isDateField(el) {
                return el && el.matches && el.matches('[data-date-display]');
            }

            function asciiDigits(value) {
                return String(value || '').replace(/[\u0660-\u0669\u06F0-\u06F9]/g, function (ch) {
                    var code = ch.charCodeAt(0);
                    return String(code - (code >= 0x06F0 ? 0x06F0 : 0x0660));
                });
            }

            function countDigits(value) {
                return value.replace(/\D/g, '').length;
            }

            // Overflowing digits carry into the next part only while typing straight through;
            // when a separator follows (editing inside an existing date) they are dropped instead.
            function format(raw, digitsOnly, caret) {
                var value = asciiDigits(raw);
                var iso = value.match(/^\s*(\d{4})[\\\/.\-](\d{1,2})[\\\/.\-](\d{1,2})\s*$/);
                if (iso) {
                    value = iso[3] + SEP + iso[2] + SEP + iso[1];
                    caret = null;
                } else if (digitsOnly) {
                    value = value.replace(/\D/g, '');
                    caret = null;
                }
                var parts = ['', '', ''];
                var index = 0;
                var pos = null;
                for (var i = 0; i < value.length; i++) {
                    if (i === caret) pos = parts[0].length + parts[1].length + parts[2].length + index;
                    var ch = value.charAt(i);
                    if (/\d/.test(ch)) {
                        if (parts[index].length === MAX[index]) {
                            if (index === 2 || SEP_RE.test(value.slice(i + 1))) continue;
                            index++;
                        }
                        parts[index] += ch;
                    } else if (SEP_RE.test(ch) && index < 2 && parts[index] !== '') {
                        index++;
                    }
                }
                var out = parts.slice(0, index + 1).join(SEP);
                return { value: out, caret: pos === null ? out.length : pos };
            }

            function pad(value) {
                var m = value.match(/^(\d{1,2})\\(\d{1,2})\\(\d{4})$/);
                return m ? ('0' + m[1]).slice(-2) + SEP + ('0' + m[2]).slice(-2) + SEP + m[3] : value;
            }

            function isValid(value) {
                var m = value.match(/^(\d{1,2})\\(\d{1,2})\\(\d{4})$/);
                if (!m) return false;
                var d = +m[1], mo = +m[2], y = +m[3];
                if (y < MIN_YEAR || y > MAX_YEAR) return false;
                var date = new Date(y, mo - 1, d);
                return date.getFullYear() === y && date.getMonth() === mo - 1 && date.getDate() === d;
            }

            function syncValidity(el) {
                el.setCustomValidity(el.value === '' || isValid(el.value) ? '' : INVALID);
            }

            function setValue(el, next, caret) {
                if (el.value === next) return;
                el.value = next;
                if (caret !== null && document.activeElement === el) {
                    el.setSelectionRange(caret, caret);
                }
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }

            document.addEventListener('input', function (event) {
                var el = event.target;
                if (!isDateField(el)) return;
                var type = event.inputType || '';
                // Deletions are left untouched so backspace never fights the mask; blur tidies up.
                if (type.indexOf('delete') !== 0) {
                    var value = el.value;
                    var caret = el.selectionStart === null ? value.length : el.selectionStart;
                    var pasted = type === 'insertFromPaste' && !/^\s*\S+[\\\/.\-]\S+[\\\/.\-]\S+\s*$/.test(value);
                    var result = format(value, pasted, caret);
                    setValue(el, result.value, result.caret);
                }
                syncValidity(el);
            });

            document.addEventListener('focusout', function (event) {
                var el = event.target;
                if (!isDateField(el) || el.value === '') return;
                var value = asciiDigits(el.value);
                var next = format(value, false, null).value;
                if (!isValid(next) && countDigits(value) === 8) {
                    next = format(value, true, null).value;
                }
                setValue(el, pad(next), null);
                syncValidity(el);
            });

            function textFieldFor(picker) {
                var wrap = picker.closest('[data-date-wrap]');
                return wrap ? wrap.querySelector('[data-date-display]') : null;
            }

            function pickerFor(field) {
                var wrap = field.closest('[data-date-wrap]');
                return wrap ? wrap.querySelector('[data-date-picker]') : null;
            }

            function openPicker(picker) {
                var field = textFieldFor(picker);
                if (!field || field.disabled || field.readOnly) return false;
                var value = pad(format(field.value, false, null).value);
                var m = isValid(value) ? value.match(/^(\d{2})\\(\d{2})\\(\d{4})$/) : null;
                picker.value = m ? m[3] + '-' + m[2] + '-' + m[1] : '';
                if (typeof picker.showPicker === 'function') {
                    try { picker.showPicker(); } catch (e) {}
                }
                return true;
            }

            document.addEventListener('click', function (event) {
                var picker = event.target;
                if (!picker || !picker.matches || !picker.matches('[data-date-picker]')) return;
                if (!openPicker(picker)) event.preventDefault();
            });

            // Dates come from the calendar only; the text field just shows the chosen day.
            document.addEventListener('keydown', function (event) {
                var el = event.target;
                if (!isDateField(el) || event.ctrlKey || event.metaKey || event.altKey) return;
                var key = event.key;
                if (key === 'Tab' || key === 'Escape') return;
                if (key === 'Enter' && el.form) return;
                event.preventDefault();
                if ((key === 'Backspace' || key === 'Delete') && !el.required) {
                    setValue(el, '', null);
                    syncValidity(el);
                    return;
                }
                if (key === ' ' || key === 'Enter' || key === 'ArrowDown') {
                    var picker = pickerFor(el);
                    if (picker) openPicker(picker);
                }
            });

            ['paste', 'drop', 'cut'].forEach(function (type) {
                document.addEventListener(type, function (event) {
                    if (isDateField(event.target)) event.preventDefault();
                });
            });

            function applyPicked(event) {
                var picker = event.target;
                if (!picker || !picker.matches || !picker.matches('[data-date-picker]')) return;
                var field = textFieldFor(picker);
                if (!field) return;
                if (!picker.value) {
                    if (event.type === 'change' && !field.required) {
                        setValue(field, '', null);
                        syncValidity(field);
                    }
                    return;
                }
                var parts = picker.value.split('-');
                setValue(field, parts[2] + SEP + parts[1] + SEP + parts[0], null);
                syncValidity(field);
            }

            document.addEventListener('change', applyPicked);
            document.addEventListener('input', applyPicked);

            function initAll() {
                document.querySelectorAll('[data-date-display]').forEach(syncValidity);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initAll);
            } else {
                initAll();
            }
        })();
    </script>
    <script src="{{ asset('assets/js/cp-ui.js') }}?v={{ @filemtime(public_path('assets/js/cp-ui.js')) ?: time() }}"></script>
    <script src="{{ asset('assets/js/cp-table-sort.js') }}?v={{ @filemtime(public_path('assets/js/cp-table-sort.js')) ?: time() }}"></script>
    @stack('scripts')
</body>
</html>
