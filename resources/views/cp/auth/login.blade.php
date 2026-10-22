<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#08A46D">
    <title>تسجيل الدخول — لوحة التحكم</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('cp-theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] },
                    colors: { primary: '#08A46D', 'primary-dark': '#069660' }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}?v={{ @filemtime(public_path('assets/css/auth.css')) ?: time() }}">
</head>
<body class="auth-page font-sans">
    <div class="auth-aurora" aria-hidden="true">
        <span></span><span></span><span></span>
    </div>
    <div class="auth-grid" aria-hidden="true"></div>

    <main class="auth-shell">
        <div class="auth-card {{ $errors->any() ? 'auth-shake' : '' }}">
            <div class="auth-head">
                <div class="auth-logo"><span class="material-symbols-outlined">account_balance_wallet</span></div>
                <h1 class="auth-title">تسجيل الدخول</h1>
                <p class="auth-sub">لوحة التحكم</p>
            </div>

            @if($errors->any())
                <div class="auth-alert auth-alert-error" role="alert">
                    <span class="material-symbols-outlined">error</span>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @if(session('success'))
                <div class="auth-alert auth-alert-success" role="status">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <form action="{{ route('cp.login') }}" method="post" class="space-y-5" data-auth-form>
                @csrf
                <div>
                    <label for="email" class="auth-label">البريد الإلكتروني</label>
                    <div class="auth-field">
                        <span class="material-symbols-outlined">alternate_email</span>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                            class="auth-input" placeholder="admin@example.com" dir="ltr">
                    </div>
                </div>
                <div>
                    <label for="password" class="auth-label">كلمة المرور</label>
                    <div class="auth-field">
                        <span class="material-symbols-outlined">lock</span>
                        <input type="password" name="password" id="password" required autocomplete="current-password"
                            class="auth-input" placeholder="••••••••" dir="ltr">
                        <button type="button" class="auth-eye" data-toggle-password aria-label="إظهار كلمة المرور">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>
                <label class="auth-remember">
                    <input type="checkbox" name="remember" id="remember" value="1">
                    <span>تذكرني</span>
                </label>
                <button type="submit" class="auth-submit">
                    <span class="material-symbols-outlined">login</span>
                    <span>تسجيل الدخول</span>
                </button>
            </form>
        </div>
        <p class="auth-foot">{{ __('ui.site_name') }}</p>
    </main>

    <script>
        (function () {
            var toggle = document.querySelector('[data-toggle-password]');
            var input = document.getElementById('password');
            toggle && toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.querySelector('.material-symbols-outlined').textContent = show ? 'visibility_off' : 'visibility';
                toggle.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
            });
            var form = document.querySelector('[data-auth-form]');
            form && form.addEventListener('submit', function () {
                var btn = form.querySelector('.auth-submit');
                if (btn) btn.classList.add('is-loading');
            });
            window.addEventListener('pageshow', function () {
                var btn = document.querySelector('.auth-submit');
                if (btn) btn.classList.remove('is-loading');
            });
        })();
    </script>
</body>
</html>
