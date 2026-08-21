<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل الدخول — لوحة التحكم</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] },
                    colors: { primary: '#08A46D', 'primary-dark': '#069660' }
                }
            }
        }
    </script>
</head>
<body class="font-sans bg-slate-100 dark:bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 p-8">
            <div class="text-center mb-8">
                <span class="material-symbols-outlined text-5xl text-primary mb-4">lock</span>
                <h1 class="text-xl font-bold text-slate-800 dark:text-white">تسجيل الدخول</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">لوحة التحكم</p>
            </div>

            @if($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @if(session('success'))
                <div class="mb-6 p-4 rounded-xl bg-primary/10 border border-primary/20 text-primary text-sm">{{ session('success') }}</div>
            @endif

            <form action="{{ route('cp.login') }}" method="post" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 px-4 py-3 focus:ring-2 focus:ring-primary/30 focus:border-primary"
                        placeholder="admin@example.com">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">كلمة المرور</label>
                    <input type="password" name="password" id="password" required
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 px-4 py-3 focus:ring-2 focus:ring-primary/30 focus:border-primary"
                        placeholder="••••••••">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" value="1" class="rounded border-slate-300 text-primary focus:ring-primary/30">
                    <label for="remember" class="ms-2 text-sm text-slate-600 dark:text-slate-400">تذكرني</label>
                </div>
                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-primary hover:bg-primary-dark text-white font-medium flex items-center justify-center gap-2 transition-colors">
                    <span class="material-symbols-outlined text-xl">login</span>
                    تسجيل الدخول
                </button>
            </form>
        </div>
        <p class="text-center text-slate-500 dark:text-slate-400 text-sm mt-6">{{ __('ui.site_name') }}</p>
    </div>
</body>
</html>
