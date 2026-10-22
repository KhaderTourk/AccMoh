<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b1220">
    <title>دخول مدير المنصة — AccMa</title>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}?v={{ @filemtime(public_path('assets/css/auth.css')) ?: time() }}">
</head>
<body class="auth-page auth-super">
    <div class="auth-aurora" aria-hidden="true">
        <span></span><span></span><span></span>
    </div>
    <div class="auth-grid" aria-hidden="true"></div>

    <main class="auth-shell">
        <form method="post" action="{{ route('super.login.submit') }}" class="auth-card {{ $errors->has('email') ? 'auth-shake' : '' }} space-y-5" data-auth-form>
            @csrf
            <div class="auth-head">
                <div class="auth-logo"><span class="material-symbols-outlined">shield_person</span></div>
                <h1 class="auth-title">مدير المنصة</h1>
                <p class="auth-sub">إنشاء وإدارة نسخ AccMa للمستخدمين</p>
            </div>

            @error('email')
                <div class="auth-alert auth-alert-error" role="alert">
                    <span class="material-symbols-outlined">error</span>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            <div>
                <label for="super-email" class="auth-label">البريد</label>
                <div class="auth-field">
                    <span class="material-symbols-outlined">alternate_email</span>
                    <input type="email" name="email" id="super-email" value="{{ old('email') }}" required autofocus autocomplete="username" class="auth-input" dir="ltr">
                </div>
            </div>
            <div>
                <label for="super-password" class="auth-label">كلمة المرور</label>
                <div class="auth-field">
                    <span class="material-symbols-outlined">lock</span>
                    <input type="password" name="password" id="super-password" required autocomplete="current-password" class="auth-input" dir="ltr">
                    <button type="button" class="auth-eye" data-toggle-password aria-label="إظهار كلمة المرور">
                        <span class="material-symbols-outlined">visibility</span>
                    </button>
                </div>
            </div>
            <label class="auth-remember"><input type="checkbox" name="remember" value="1"> <span>تذكرني</span></label>
            <button class="auth-submit"><span class="material-symbols-outlined">login</span><span>دخول</span></button>
        </form>
        <p class="auth-foot">AccMa · لوحة المنصة</p>
    </main>

    <script>
        (function () {
            var toggle = document.querySelector('[data-toggle-password]');
            var input = document.getElementById('super-password');
            toggle && toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.querySelector('.material-symbols-outlined').textContent = show ? 'visibility_off' : 'visibility';
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
