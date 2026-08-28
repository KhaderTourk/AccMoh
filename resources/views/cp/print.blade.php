<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'تقرير' }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; direction: rtl; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        .muted { color: #64748b; font-size: 11px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: right; }
        th { background: #f1f5f9; }
        .card { border: 1px solid #e2e8f0; padding: 10px; margin: 0 0 10px; }
        .grid td { border: none; padding: 4px 8px 4px 0; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
