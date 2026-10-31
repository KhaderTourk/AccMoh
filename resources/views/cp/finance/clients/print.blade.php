<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
@php
    use App\Support\Money;

    // كشف حساب الزبون (تصميم #7 §5): ورقة بيضاء موفّرة للحبر، خط وردي فوق، رأس جدول أبيض بخط كحلي.
    $filterType = $filterType ?? 'all';
    $filterKinds = $filterKinds ?? [];
    $filtered = $filterType !== 'all';
    $docTitle = ['all' => 'كشف حساب', 'svc' => 'كشف خدمات', 'pay' => 'كشف دفعات', 'other' => 'كشف حركات أخرى'][$filterType] ?? 'كشف حساب';
    if ($filterKinds) {
        $docTitle .= ' · '.implode('، ', $filterKinds);
    }
    $typeLabels = ['service' => 'خدمة', 'payment' => 'دفعة', 'memo' => 'رصيد سابق', 'goods' => 'بضاعة / خصم', 'refund' => 'استرداد'];

    $chronological = collect($timeline)
        ->sortBy(fn ($m) => $m['date']->format('Y-m-d').sprintf('%010d', $m['sort_at'] ?? 0).sprintf('%010d', $m['id'] ?? 0))
        ->values();
    $byCurrency = $chronological->groupBy(fn ($m) => (int) $m['currency']->id);
    $summaryBy = collect($summaries ?? [])->keyBy(fn ($r) => (int) $r['currency']->id);
    $multiCurrency = $byCurrency->count() > 1;

    if ($from || $to) {
        $periodText = ($from ? format_date($from) : 'البداية').' — '.($to ? format_date($to) : format_date(now()));
    } elseif ($chronological->isNotEmpty()) {
        $periodText = 'كل الوقت: '.format_date($chronological->first()['date']).' — '.format_date($chronological->last()['date']);
    } else {
        $periodText = 'كل الوقت';
    }
    $dueDate = $to ? format_date($to) : format_date(now());
    $logo = public_path('assets/icons/brand-logo.png');
@endphp
<title>{{ $docTitle }} — {{ $client->personName() }}</title>
@include('cp.finance.partials.statement-style')
</head>
<body>
<div class="topline"></div>
<table class="head">
    <tr>
        <td style="width:46px;">@if(is_file($logo))<img src="{{ $logo }}" style="width:40px;height:40px;">@endif</td>
        <td>
            <div class="co">براند للحلول الإبداعية المتكاملة</div>
            <div class="co-sub">حسابات براند المالية</div>
        </td>
        <td>
            <div class="doc">{{ $docTitle }}</div>
            <div class="doc-meta">رقم الكشف: {{ $statementNo ?? ('C'.$client->id) }} · تاريخ الإصدار: {{ format_date(now()) }}</div>
        </td>
    </tr>
</table>

<table class="cards">
    <tr>
        <td style="width:58%;">
            <div class="lbl">الزبون</div>
            <div class="val">{{ $client->personName() }}</div>
            @if(filled($client->organization()) || filled($client->phone))
                <div class="small">{{ trim(implode(' · ', array_filter([$client->organization(), $client->phone]))) }}</div>
            @endif
        </td>
        <td>
            <div class="lbl">الفترة</div>
            <div class="val" style="font-size:10px;">{{ $periodText }}</div>
            <div class="small">عدد الحركات: {{ $chronological->count() }}</div>
        </td>
    </tr>
</table>

@if($byCurrency->isNotEmpty())
<table class="sum">
    <tr>
        @if($multiCurrency)<th>العملة</th>@endif
        @unless($filtered)<th>رصيد سابق</th>@endunless
        <th>مدين (عليه)</th>
        <th>دائن (له)</th>
        <th>{{ $filtered ? 'المجموع' : 'الرصيد' }}</th>
    </tr>
    @foreach($byCurrency as $cid => $items)
        @php
            $cur = $items->first()['currency'];
            $dr = '0.00'; $crd = '0.00';
            foreach ($items as $m) {
                $d = (string) $m['delta'];
                if (Money::isPositive($d)) { $dr = Money::add($dr, $d); }
                elseif (Money::isNegative($d)) { $crd = Money::add($crd, Money::abs($d)); }
            }
            $open = (string) ($summaryBy[$cid]['opening'] ?? '0.00');
            $end = $filtered ? Money::sub($dr, $crd) : Money::add($open, Money::sub($dr, $crd));
        @endphp
        <tr>
            @if($multiCurrency)<td>{{ $cur->name }}</td>@endif
            @unless($filtered)<td>{{ $cur->format($open) }}</td>@endunless
            <td class="dr">{{ $cur->format($dr) }}</td>
            <td class="cr">{{ $cur->format($crd) }}</td>
            <td>{{ $cur->format(Money::abs($end)) }}{{ Money::isNegative($end) ? ' (له)' : '' }}</td>
        </tr>
    @endforeach
</table>
@endif

@forelse($byCurrency as $cid => $items)
    @php
        $cur = $items->first()['currency'];
        $open = (string) ($summaryBy[$cid]['opening'] ?? '0.00');
        $running = $filtered ? '0.00' : $open;
        $dr = '0.00'; $crd = '0.00';
    @endphp
    @if($multiCurrency)<div class="cur">{{ $cur->name }}</div>@endif
    <table class="st">
        <thead>
            <tr>
                <th>م</th>
                <th style="width:58px;">التاريخ</th>
                <th>البيان</th>
                <th style="width:72px;">مدين<span>عليه</span></th>
                <th style="width:72px;">دائن<span>له</span></th>
                <th style="width:80px;">{{ $filtered ? 'المجموع' : 'الرصيد' }}</th>
            </tr>
        </thead>
        <tbody>
        @unless($filtered)
            <tr class="open">
                <td class="n">—</td>
                <td>{{ $from ? format_date($from) : '—' }}</td>
                <td class="desc">رصيد سابق{{ !empty($summaryBy[$cid]['opening_overridden']) ? ' (معدّل للعرض)' : '' }}</td>
                <td></td><td></td>
                <td>{{ $cur->format($open) }}</td>
            </tr>
        @endunless
        @foreach($items as $i => $m)
            @php
                $d = (string) $m['delta'];
                $isCredit = Money::isNegative($d) || (Money::isZero($d) && in_array($m['type'], ['payment', 'goods'], true));
                if (Money::isPositive($d)) { $dr = Money::add($dr, $d); }
                elseif (Money::isNegative($d)) { $crd = Money::add($crd, Money::abs($d)); }
                $running = Money::add($running, $d);
                $amt = $cur->format($m['amount']);
                $title = preg_replace('/^(خدمة|بضاعة مأخوذة): /u', '', $m['title']);
            @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="n">{{ $i + 1 }}</td>
                <td>{{ format_date($m['date']) }}</td>
                <td class="desc">
                    {{ $title }}
                    <div class="t">{{ $m['kind'] ?? ($typeLabels[$m['type']] ?? '') }}@if(!empty($m['fx'])) · سعر الصرف: {{ $m['fx'] }}@endif @if(Money::isZero($d)) · لا تُحتسب في الرصيد @endif</div>
                    @if(filled($m['notes'] ?? null))<div class="t" style="white-space:pre-line;">{{ $m['notes'] }}</div>@endif
                </td>
                <td class="dr">{{ $isCredit ? '' : $amt }}</td>
                <td class="cr">{{ $isCredit ? $amt : '' }}</td>
                <td>{{ $cur->format(Money::abs($running)) }}{{ Money::isNegative($running) ? ' له' : '' }}</td>
            </tr>
        @endforeach
            <tr class="tot">
                <td></td><td></td>
                <td class="desc">المجموع</td>
                <td class="dr">{{ $cur->format($dr) }}</td>
                <td class="cr">{{ $cur->format($crd) }}</td>
                <td>{{ $cur->format(Money::abs($running)) }}{{ Money::isNegative($running) ? ' له' : '' }}</td>
            </tr>
        </tbody>
    </table>
@empty
    <p class="small" style="text-align:center;padding:14px;">لا حركات{{ ($from || $to) ? ' في هذه الفترة' : '' }}.</p>
@endforelse

@php
    $dueRows = collect($summaries ?? [])->filter(fn ($r) => ! Money::isZero($r['closing']));
@endphp
<div class="due">
    الرصيد المستحق بتاريخ {{ $dueDate }}:
    @forelse($dueRows as $r)
        {{ $r['currency']->format(Money::abs($r['closing'])) }} {{ Money::isNegative($r['closing']) ? '(له)' : '(عليه)' }}@if(!$loop->last) · @endif
    @empty
        الحساب مسدّد
    @endforelse
</div>

<ol class="terms">
    <li>يُعدّ هذا الكشف ملزماً ما لم يصلنا اعتراض مكتوب خلال 15 يوماً من تاريخ استلامه.</li>
    <li>الخطأ والسهو مستدرك.</li>
    <li>المبالغ بالعملات الأخرى محسوبة بسعر صرف يومها.</li>
    <li>هذا الكشف ليس إيصال قبض.</li>
    <li>يرجى مطابقة الكشف مع سجلاتكم.</li>
</ol>

<table class="sign">
    <tr>
        <td>توقيع المحاسب</td>
        <td>ختم الشركة</td>
    </tr>
    <tr>
        <td class="line">&nbsp;</td>
        <td class="line">&nbsp;</td>
    </tr>
</table>
</body>
</html>
