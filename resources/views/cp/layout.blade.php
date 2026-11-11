<!DOCTYPE html>
<html class="cp-admin" dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FB005B">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>@yield('title', 'الرئيسية') — حسابات براند</title>
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
    @include('cp.partials.keep-view')
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    {{-- Non-blocking fonts: don't stall first paint on slow networks --}}
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Tajawal:wght@400;500;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
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
    <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}?v={{ @filemtime(public_path('assets/css/brand.css')) ?: time() }}">
    @stack('styles')
</head>
<body class="@yield('body_class') font-sans bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen transition-colors duration-300">
    <div class="cp-wrap flex min-h-screen">
        @php
            $bsOn = fn (...$r) => request()->routeIs(...$r) ? 'on' : '';
            $bsBiz = cpCan('finance') && tenantBusinessEnabled();
            $bsUser = auth()->user();
            // آخر القائمة زي الصورة المعتمدة: التحويلات والإعدادات. باقي الصفحات تحت «صفحات ثانية» عشان تضل موجودة.
            $bsApiPending = cpCan('finance') ? \App\Models\ApiOperation::query()->pending()->where('created_at', '>=', now()->subDay())->count() : 0;
            $bsExtra = [];
            if (cpCan('finance')) {
                $bsExtra[] = ['ledger', 'سجل الحركات', route('cp.ledger.index'), $bsOn('cp.ledger.*')];
                $bsExtra[] = ['reports', 'التقارير', route('cp.reports.index'), $bsOn('cp.reports.*')];
                $bsExtra[] = ['api', 'عمليات الـ API', route('cp.api-operations.index'), $bsOn('cp.api-operations.*')];
            }
            $bsExtra[] = ['offline', 'وضع عدم الاتصال', route('cp.offline'), $bsOn('cp.offline')];
            if (cpCan('users')) {
                $bsExtra[] = ['users', 'المستخدمون', route('cp.users.index'), $bsOn('cp.users.*')];
            }
            $bsExtraOpen = request()->routeIs('cp.ledger.*', 'cp.reports.*', 'cp.api-operations.*', 'cp.offline', 'cp.users.*');
        @endphp
        <aside id="cp-sidebar" class="bs-side" aria-label="القائمة الجانبية">
            <a href="{{ route('cp.dashboard') }}" class="bs-logo">
                <img src="{{ asset('assets/icons/brand-logo.png') }}" alt="براند" width="34" height="34">
                <span>حسابات براند<small>إدارة مالية داخلية</small></span>
            </a>
            @if(cpCan('finance'))
            <button type="button" data-quick-open class="bs-quick"><span>＋ تسجيل سريع</span><kbd>N</kbd></button>
            @endif
            <a href="{{ route('cp.dashboard') }}" class="bs-link {{ $bsOn('cp.dashboard') }}">@include('cp.partials.bs-icon', ['name' => 'home'])الرئيسية</a>
            @if(cpCan('finance'))
            <a href="{{ route('cp.balances.index') }}" class="bs-link {{ $bsOn('cp.balances.*') }}">@include('cp.partials.bs-icon', ['name' => 'drawers'])الأدراج والأرصدة</a>
            @if($bsBiz)
            <div class="bs-sec">@include('cp.partials.bs-icon', ['name' => 'work'])الشغل</div>
            <div class="bs-sub">
                <a href="{{ route('cp.clients.index') }}" class="bs-link {{ $bsOn('cp.clients.*', 'cp.client-services.*') }}">الزبائن</a>
                <a href="{{ route('cp.workers.index') }}" class="bs-link {{ $bsOn('cp.workers.*') }}">الموظفين</a>
                <a href="{{ route('cp.suppliers.index') }}" class="bs-link {{ $bsOn('cp.suppliers.*') }}">الموردين</a>
                <a href="{{ route('cp.ads.index') }}" class="bs-link {{ $bsOn('cp.ads.*') }}">الإعلانات الممولة</a>
            </div>
            @endif
            <div class="bs-sec">@include('cp.partials.bs-icon', ['name' => 'person'])الشخصي</div>
            <div class="bs-sub">
                <a href="{{ route('cp.persons.index') }}" class="bs-link {{ $bsOn('cp.persons.*') }}">الأشخاص</a>
            </div>
            @endif
            <div class="bs-foot">
                @if(cpCan('finance'))
                <a href="{{ route('cp.transfers.index') }}" class="bs-link {{ $bsOn('cp.transfers.*') }}">@include('cp.partials.bs-icon', ['name' => 'transfer'])التحويلات</a>
                @endif
                <details class="bs-extra" {{ $bsExtraOpen ? 'open' : '' }}>
                    <summary class="bs-link">@include('cp.partials.bs-icon', ['name' => 'more'])صفحات ثانية<span id="cp-offline-badge" class="bs-badge hidden">0</span></summary>
                    <div class="bs-sub">
                        @foreach($bsExtra as [$ic, $label, $url, $on])
                        <a href="{{ $url }}" class="bs-link {{ $on }}">
                            {{ $label }}
                            @if($ic === 'api' && $bsApiPending)
                            <span class="bs-badge" title="عمليات آخر 24 ساعة">{{ $bsApiPending }}</span>
                            @endif
                        </a>
                        @endforeach
                    </div>
                </details>
                @if($bsBiz)
                <a href="{{ route('cp.settings.index') }}" class="bs-link {{ $bsOn('cp.settings.*') }}">@include('cp.partials.bs-icon', ['name' => 'gear'])الإعدادات</a>
                @endif
            </div>
            @if($bsUser)
            <div class="bs-user">
                <span class="bs-av">{{ mb_substr($bsUser->name, 0, 1) }}</span>
                <span class="bs-who"><b>{{ $bsUser->name }}</b><small data-net><i class="bs-dot"></i><span>متصل</span></small></span>
                <button type="button" class="bs-ubtn" data-theme-toggle aria-label="تبديل الوضع الليلي" title="الوضع الليلي">@include('cp.partials.bs-icon', ['name' => 'moon'])</button>
                <form action="{{ route('cp.logout') }}" method="post">
                    @csrf
                    <button type="submit" class="bs-ubtn" aria-label="تسجيل الخروج" title="تسجيل الخروج">@include('cp.partials.bs-icon', ['name' => 'logout'])</button>
                </form>
            </div>
            @endif
        </aside>

        <div class="cp-main flex-1 flex flex-col min-w-0">
            {{-- الشريط اللي فوق انشال (قرار 11/11/2026): الحساب والوضع الليلي والخروج بالقائمة الجانبية، و«بحث سريع» موقوف حالياً. --}}
            <header class="cp-header bs-head">
                <h1>@yield('title', 'الرئيسية')</h1>
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
                @if($bsBiz && request()->routeIs('cp.clients.index', 'cp.workers.index', 'cp.suppliers.index'))
                {{-- مفتاح «الشغل» على الموبايل بس --}}
                <nav class="bs-wseg" aria-label="الشغل">
                    <a href="{{ route('cp.clients.index') }}" data-work="clients" class="{{ $bsOn('cp.clients.index') }}">الزبائن</a>
                    <a href="{{ route('cp.workers.index') }}" data-work="workers" class="{{ $bsOn('cp.workers.index') }}">الموظفين</a>
                    <a href="{{ route('cp.suppliers.index') }}" data-work="suppliers" class="{{ $bsOn('cp.suppliers.index') }}">الموردين</a>
                </nav>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    @if(cpCan('finance'))
        @include('cp.partials.quick-entry')
    @endif
    {{-- الموبايل (قرار 11/11/2026): الرئيسية، الشغل، الشخصي، الأدراج، المزيد. «الشغل» بيفتح على آخر قائمة استعملتها. --}}
    <nav class="bs-tabbar" aria-label="التنقل">
        <a href="{{ route('cp.dashboard') }}" class="{{ $bsOn('cp.dashboard') }}">@include('cp.partials.bs-icon', ['name' => 'home', 'size' => 23])الرئيسية</a>
        @if($bsBiz)
        <a href="{{ route('cp.clients.index') }}" data-work-tab class="{{ $bsOn('cp.clients.*', 'cp.client-services.*', 'cp.workers.*', 'cp.suppliers.*') }}">@include('cp.partials.bs-icon', ['name' => 'work', 'size' => 23])الشغل</a>
        @endif
        @if(cpCan('finance'))
        <a href="{{ route('cp.persons.index') }}" class="{{ $bsOn('cp.persons.*') }}">@include('cp.partials.bs-icon', ['name' => 'person', 'size' => 23])الشخصي</a>
        <a href="{{ route('cp.balances.index') }}" class="{{ $bsOn('cp.balances.*') }}">@include('cp.partials.bs-icon', ['name' => 'drawers', 'size' => 23])الأدراج</a>
        @endif
        <button type="button" id="cp-more-open" aria-haspopup="dialog" aria-controls="cp-more-sheet">@include('cp.partials.bs-icon', ['name' => 'more', 'size' => 23])المزيد</button>
    </nav>
    <div class="bs-more" id="cp-more-sheet" hidden>
        <div role="dialog" aria-label="المزيد">
            <span class="bs-grab"></span>
            @if($bsBiz)
            <a href="{{ route('cp.ads.index') }}" class="{{ $bsOn('cp.ads.*') }}">@include('cp.partials.bs-icon', ['name' => 'ads', 'size' => 20])الإعلانات الممولة</a>
            @endif
            @if(cpCan('finance'))
            <a href="{{ route('cp.transfers.index') }}" class="{{ $bsOn('cp.transfers.*') }}">@include('cp.partials.bs-icon', ['name' => 'transfer', 'size' => 20])التحويلات</a>
            @endif
            @foreach($bsExtra as [$ic, $label, $url, $on])
            <a href="{{ $url }}" class="{{ $on }}">@include('cp.partials.bs-icon', ['name' => $ic, 'size' => 20]){{ $label }}</a>
            @endforeach
            @if($bsBiz)
            <a href="{{ route('cp.settings.index') }}" class="{{ $bsOn('cp.settings.*') }}">@include('cp.partials.bs-icon', ['name' => 'gear', 'size' => 20])الإعدادات</a>
            @endif
            <button type="button" data-theme-toggle>@include('cp.partials.bs-icon', ['name' => 'moon', 'size' => 20])الوضع الليلي</button>
            @if($bsUser)
            <form action="{{ route('cp.logout') }}" method="post">
                @csrf
                <button type="submit">@include('cp.partials.bs-icon', ['name' => 'logout', 'size' => 20])تسجيل الخروج<small data-net-line>{{ $bsUser->name }} · <span>متصل</span></small></button>
            </form>
            @endif
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
            // لوحة «المزيد» على الموبايل
            (function () {
                var sheet = document.getElementById('cp-more-sheet');
                var openBtn = document.getElementById('cp-more-open');
                var fab = document.getElementById('qeFab');
                if (!sheet || !openBtn) return;
                function set(open) { sheet.hidden = !open; openBtn.classList.toggle('on', open); if (fab) fab.style.visibility = open ? 'hidden' : ''; }
                openBtn.addEventListener('click', function () { set(sheet.hidden); });
                sheet.addEventListener('click', function (e) { if (e.target === sheet) set(false); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) set(false); });
            })();

            // «الشغل» على الموبايل بيفتح على آخر قائمة استعملتها (زبائن، موظفين، موردين)
            (function () {
                var KEY = 'cp-last-work';
                var cur = document.querySelector('.bs-wseg a.on');
                try {
                    if (cur) localStorage.setItem(KEY, cur.dataset.work);
                    var last = localStorage.getItem(KEY), tab = document.querySelector('[data-work-tab]');
                    if (tab && last && !cur) {
                        var urls = @json(['clients' => route('cp.clients.index'), 'workers' => route('cp.workers.index'), 'suppliers' => route('cp.suppliers.index')]);
                        if (urls[last]) tab.href = urls[last];
                    }
                } catch (e) {}
            })();

            document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const apply = function () {
                        document.documentElement.classList.toggle('dark');
                        localStorage.setItem('cp-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                    };
                    if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) document.startViewTransition(apply);
                    else apply();
                });
            });

            if (localStorage.getItem('cp-theme') === 'dark') document.documentElement.classList.add('dark');
            else if (localStorage.getItem('cp-theme') === 'light') document.documentElement.classList.remove('dark');
        })();

        // Offline status + Service Worker
        (function() {
            function paintNet() {
                const online = navigator.onLine;
                document.querySelectorAll('[data-net]').forEach(function (el) {
                    el.classList.toggle('is-off', !online);
                    el.querySelector('span').textContent = online ? 'متصل' : 'بدون نت';
                });
                document.querySelectorAll('[data-net-line] span').forEach(function (el) { el.textContent = online ? 'متصل' : 'بدون نت'; });
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
