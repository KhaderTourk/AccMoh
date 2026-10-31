<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'تقرير' }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; direction: rtl; font-size: 10.5px; color: #1e293b; }

        /* الترويسة */
        table.hero { width: 100%; border-collapse: collapse; margin: 0 0 14px; }
        table.hero td { vertical-align: middle; }
        .hero-main { background: #0B2537; color: #fff; padding: 16px 18px; border-radius: 10px; }
        .hero-brand { font-size: 8.5px; letter-spacing: 2px; color: #ffc2d7; margin: 0 0 4px; }
        .hero-title { font-size: 19px; font-weight: bold; color: #fff; margin: 0; }
        .hero-meta { font-size: 9.5px; color: #ffe0eb; margin-top: 6px; }
        .hero-doc { text-align: left; }
        .hero-doc-type { font-size: 13px; font-weight: bold; color: #ffffff; }
        .hero-doc-date { font-size: 9px; color: #ffc2d7; margin-top: 3px; }

        /* للتوافق مع القوالب الأخرى */
        .hero { background: #0B2537; color: #fff; padding: 16px 18px; margin: 0 0 14px; border-radius: 10px; }
        .hero .brand { font-size: 8.5px; letter-spacing: 2px; color: #ffc2d7; margin: 0 0 4px; }
        .hero h1 { font-size: 19px; margin: 0; color: #fff; }
        .hero .meta { font-size: 9.5px; color: #ffe0eb; margin-top: 6px; }

        .period { background: #fff0f5; border: 1px solid #ffc2d7; color: #0B2537; padding: 8px 12px; margin: 0 0 14px; font-size: 10.5px; border-radius: 6px; }
        h2 { font-size: 12.5px; color: #0B2537; margin: 18px 0 8px; padding: 0 8px 0 0; border-right: 4px solid #FB005B; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th { background: #0B2537; color: #fff; padding: 7px 8px; text-align: right; font-size: 9.5px; font-weight: bold; }
        table.data td { padding: 6px 8px; text-align: right; border-bottom: 1px solid #e2e8f0; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data .total td { background: #fff0f5; font-weight: bold; border-top: 1px solid #ffc2d7; }
        table.data .empty { text-align: center; color: #64748b; padding: 14px; }

        table.kpis { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 0 -8px 10px; }
        table.kpis td { width: 33%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 12px; vertical-align: top; border-radius: 8px; }
        .kpi-label { font-size: 9.5px; color: #64748b; margin-bottom: 4px; }
        .kpi-value { font-size: 14px; font-weight: bold; color: #047857; }
        .kpi-value.neg { color: #be123c; }
        .card { border: 1px solid #fde68a; background: #fffbeb; padding: 10px 12px; margin: 0 0 12px; border-radius: 8px; }
        .muted { color: #64748b; font-size: 9.5px; }
        .amount { font-weight: bold; }
        .sub { font-size: 8.5px; color: #64748b; }

        /* كشف الحساب */
        table.stmt { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.stmt th { background: #0B2537; color: #fff; padding: 8px 6px; font-size: 9.5px; font-weight: bold; text-align: center; border: 1px solid #0B2537; }
        table.stmt td { padding: 6px 6px; border: 1px solid #e2e8f0; text-align: center; vertical-align: top; }
        table.stmt td.desc { text-align: right; }
        table.stmt tr.alt td { background: #f8fafc; }
        table.stmt tr.opening td { background: #fffbeb; color: #92400e; font-weight: bold; }
        table.stmt td.debit { color: #9f1239; font-weight: bold; }
        table.stmt td.credit { color: #047857; font-weight: bold; }
        table.stmt td.bal { font-weight: bold; color: #0f172a; background: #f1f5f9; }
        table.stmt td.num { color: #94a3b8; font-size: 9px; width: 22px; }
        .type-tag { font-size: 8.5px; font-weight: bold; padding: 2px 6px; border-radius: 4px; }
        .t-service { background: #fee2e2; color: #9f1239; }
        .t-payment { background: #ffe0eb; color: #0B2537; }
        .t-memo { background: #fef3c7; color: #92400e; }
        .t-goods { background: #e0f2fe; color: #075985; }

        table.stmt-foot { width: 100%; border-collapse: collapse; margin: 0 0 16px; }
        table.stmt-foot td { padding: 9px 10px; font-size: 10.5px; border: 1px solid #cbd5e1; }
        table.stmt-foot td.label { background: #f1f5f9; color: #334155; font-weight: bold; width: 26%; }
        table.stmt-foot td.val { font-weight: bold; text-align: center; width: 24%; }
        table.stmt-foot td.val-debit { color: #9f1239; }
        table.stmt-foot td.val-credit { color: #047857; }
        table.stmt-foot tr.grand td { background: #0B2537; color: #fff; font-size: 12px; border-color: #0B2537; }
        table.stmt-foot tr.grand td.label { background: #0B2537; color: #fff; }

        .cur-title { font-size: 11px; font-weight: bold; color: #0f172a; margin: 14px 0 6px; }
        .cur-chip { background: #fff0f5; color: #0B2537; padding: 2px 8px; border-radius: 4px; font-size: 9.5px; }
    </style>
</head>
<body>
    <table class="hero">
        <tr>
            <td class="hero-main">
                <table width="100%" style="border-collapse:collapse;">
                    <tr>
                        <td style="vertical-align:middle;">
                            <p class="hero-brand">BRAND</p>
                            <p class="hero-title">{{ $title ?? 'تقرير' }}</p>
                            <p class="hero-meta">
                                @if(!empty($subtitle))
                                    {{ $subtitle }}
                                @endif
                                @if(!empty($periodLabel))
                                    @if(!empty($subtitle)) — @endif
                                    الفترة: {{ $periodLabel }}
                                @endif
                            </p>
                        </td>
                        <td class="hero-doc" style="vertical-align:middle;">
                            <div class="hero-doc-type">{{ $docType ?? 'كشف حساب' }}</div>
                            @if(!empty($exportedAt))
                                <div class="hero-doc-date">صُدّر في {{ $exportedAt }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @yield('content')
</body>
</html>
