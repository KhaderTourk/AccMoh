<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دخول مدير المنصة — AccMa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>body{font-family:Tajawal,sans-serif}</style>
</head>
<body class="min-h-screen bg-slate-900 text-white flex items-center justify-center p-4">
<form method="post" action="{{ route('super.login.submit') }}" class="w-full max-w-md rounded-2xl bg-slate-800 border border-slate-700 p-6 space-y-4">
    @csrf
    <h1 class="text-2xl font-extrabold">مدير المنصة</h1>
    <p class="text-sm text-slate-400">إنشاء وإدارة نسخ AccMa للمستخدمين</p>
    @error('email')<p class="text-sm text-rose-400">{{ $message }}</p>@enderror
    <div>
        <label class="text-sm text-slate-300">البريد</label>
        <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-xl bg-slate-900 border border-slate-600 px-3 py-2">
    </div>
    <div>
        <label class="text-sm text-slate-300">كلمة المرور</label>
        <input type="password" name="password" required class="mt-1 w-full rounded-xl bg-slate-900 border border-slate-600 px-3 py-2">
    </div>
    <label class="inline-flex items-center gap-2 text-sm text-slate-400"><input type="checkbox" name="remember" value="1"> تذكرني</label>
    <button class="w-full rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold py-2.5">دخول</button>
</form>
</body>
</html>
