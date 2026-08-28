<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'تقرير' }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; direction: rtl; font-size: 11px; color: #1e293b; }
        .hero { background: #08A46D; color: #fff; padding: 16px 18px; margin: 0 0 14px; }
        .hero .brand { font-size: 10px; letter-spacing: 1px; color: #d1fae5; margin: 0 0 4px; }
        .hero h1 { font-size: 20px; margin: 0; color: #fff; }
        .hero .meta { font-size: 10px; color: #ecfdf5; margin-top: 6px; }
        .period { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 8px 12px; margin: 0 0 14px; font-size: 11px; }
        h2 { font-size: 13px; color: #047857; margin: 16px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #08A46D; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th { background: #08A46D; color: #fff; padding: 7px 8px; text-align: right; font-size: 10px; }
        table.data td { padding: 6px 8px; text-align: right; border-bottom: 1px solid #e2e8f0; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data .total td { background: #ecfdf5; font-weight: bold; border-top: 1px solid #a7f3d0; }
        table.data .empty { text-align: center; color: #64748b; padding: 14px; }
        table.kpis { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 0 -8px 10px; }
        table.kpis td { width: 33%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 12px; vertical-align: top; }
        .kpi-label { font-size: 10px; color: #64748b; margin-bottom: 4px; }
        .kpi-value { font-size: 14px; font-weight: bold; color: #047857; }
        .kpi-value.neg { color: #be123c; }
        .card { border: 1px solid #d1fae5; background: #f0fdf4; padding: 10px 12px; margin: 0 0 10px; }
        .muted { color: #64748b; font-size: 10px; }
        .amount { font-weight: bold; }
        .sub { font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <div class="hero">
        <p class="brand">ACCMA</p>
        <h1>{{ $title ?? 'تقرير' }}</h1>
        <p class="meta">
            @if(!empty($subtitle))
                {{ $subtitle }}
            @endif
            @if(!empty($periodLabel))
                @if(!empty($subtitle)) — @endif
                الفترة: {{ $periodLabel }}
            @endif
            @if(!empty($exportedAt))
                @if(!empty($subtitle) || !empty($periodLabel)) — @endif
                صُدّر في {{ $exportedAt }}
            @endif
        </p>
    </div>
    @yield('content')
</body>
</html>
