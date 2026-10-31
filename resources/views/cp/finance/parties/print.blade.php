<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
@php
    // كشف حساب الموظف أو المورد أو الشخص (#7 §7)، بنفس شكل كشف الزبون.
    // الشخص حساب شخصي: عنوانه «كشف حساب شخصي» وبدون البنود وختم الشركة.
    $legal = $profile['legal'];
    $byCurrency = collect($rows)->groupBy('currency');
    $codes = collect(array_keys($opening))->merge($byCurrency->keys())->unique()->values();
    $multi = $codes->count() > 1;
    $symbols = collect($rows)->mapWithKeys(fn ($m) => [$m['currency'] => $m['symbol']])->all();
    $fmt = fn ($v, $code) => number_format(abs((float) $v), 2).' '.($symbols[$code] ?? ($code === 'ILS' ? '₪' : $code));
    if ($from || $to) {
        $periodText = ($from ? format_date($from) : 'البداية').' — '.($to ? format_date($to) : format_date(now()));
    } elseif ($rows) {
        $periodText = 'كل الوقت: '.format_date($rows[0]['date']).' — '.format_date(end($rows)['date']);
    } else {
        $periodText = 'كل الوقت';
    }
    $word = fn ($v) => abs($v) < 0.005 ? '' : ' ('.($v > 0 ? $profile['pos'] : $profile['neg']).')';
    $logo = public_path('assets/icons/brand-logo.png');
@endphp
<title>{{ $docTitle }} — {{ $name }}</title>
@include('cp.finance.partials.statement-style')
</head>
<body>
<div class="topline"></div>
<table class="head">
    <tr>
        @if($legal)
            <td style="width:46px;">@if(is_file($logo))<img src="{{ $logo }}" style="width:40px;height:40px;">@endif</td>
            <td><div class="co">براند للحلول الإبداعية المتكاملة</div><div class="co-sub">حسابات براند المالية</div></td>
        @else
            <td><div class="co">حساب شخصي</div><div class="co-sub">م. محمد عمار الشيخ</div></td>
        @endif
        <td>
            <div class="doc">{{ $docTitle }}</div>
            <div class="doc-meta">رقم الكشف: {{ $statementNo }} · تاريخ الإصدار: {{ format_date(now()) }}</div>
        </td>
    </tr>
</table>

<table class="cards">
    <tr>
        <td style="width:58%;">
            <div class="lbl">{{ $profile['noun'] }}</div>
            <div class="val">{{ $name }}</div>
            @if(filled($subtitle))<div class="small">{{ $subtitle }}</div>@endif
        </td>
        <td>
            <div class="lbl">الفترة</div>
            <div class="val" style="font-size:10px;">{{ $periodText }}</div>
            <div class="small">عدد الحركات: {{ count($rows) }}</div>
        </td>
    </tr>
</table>

@forelse($codes as $code)
    @php
        $items = $byCurrency[$code] ?? collect();
        $open = $filtered && ! $from ? 0 : (float) ($opening[$code] ?? 0);
        $run = $open; $dr = 0; $cr = 0;
    @endphp
    @if($multi)<div class="cur">{{ $code }}</div>@endif
    <table class="st">
        <thead>
            <tr>
                <th>م</th>
                <th style="width:58px;">التاريخ</th>
                <th>البيان</th>
                <th style="width:72px;">مدين<span>{{ $profile['pos'] }}</span></th>
                <th style="width:72px;">دائن<span>{{ $profile['neg'] }}</span></th>
                <th style="width:84px;">{{ $filtered ? 'المجموع' : 'الرصيد' }}</th>
            </tr>
        </thead>
        <tbody>
        @if($from)
            <tr class="open"><td class="n">—</td><td>{{ format_date($from) }}</td><td class="desc">رصيد سابق</td><td></td><td></td><td>{{ $fmt($open, $code) }}{{ $word($open) }}</td></tr>
        @endif
        @foreach($items as $i => $m)
            @php
                $d = (float) $m['delta'];
                $run += $d;
                if ($d > 0) { $dr += $d; } else { $cr -= $d; }
            @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="n">{{ $i + 1 }}</td>
                <td>{{ format_date($m['date']) }}</td>
                <td class="desc">
                    {{ $m['title'] }}
                    <div class="t">{{ $m['kind'] }}@if(!empty($m['fx'])) · سعر الصرف: {{ $m['fx'] }}@endif</div>
                    @if(filled($m['notes']))<div class="t" style="white-space:pre-line;">{{ $m['notes'] }}</div>@endif
                </td>
                <td class="dr">{{ $d > 0 ? $fmt($d, $code) : '' }}</td>
                <td class="cr">{{ $d < 0 ? $fmt($d, $code) : '' }}</td>
                <td>{{ $fmt($run, $code) }}{{ $word($run) }}</td>
            </tr>
        @endforeach
            <tr class="tot">
                <td></td><td></td><td class="desc">المجموع</td>
                <td class="dr">{{ $fmt($dr, $code) }}</td>
                <td class="cr">{{ $fmt($cr, $code) }}</td>
                <td>{{ $fmt($run, $code) }}{{ $word($run) }}</td>
            </tr>
        </tbody>
    </table>
@empty
    <p class="small" style="text-align:center;padding:14px;">لا حركات{{ ($from || $to) ? ' في هذه الفترة' : '' }}.</p>
@endforelse

<div class="due">
    الرصيد بتاريخ {{ format_date(now()) }}:
    @php($nonZero = collect($balance)->filter(fn ($v) => abs($v) >= 0.005))
    @forelse($nonZero as $code => $v)
        {{ $fmt($v, $code) }}{{ $word($v) }}@if(!$loop->last) · @endif
    @empty
        الحساب مسدّد
    @endforelse
</div>

@if($legal)
<ol class="terms">
    <li>يُعدّ هذا الكشف ملزماً ما لم يصلنا اعتراض مكتوب خلال 15 يوماً من تاريخ استلامه.</li>
    <li>الخطأ والسهو مستدرك.</li>
    <li>المبالغ بالعملات الأخرى محسوبة بسعر صرف يومها.</li>
    <li>هذا الكشف ليس إيصال قبض.</li>
    <li>يرجى مطابقة الكشف مع سجلاتكم.</li>
</ol>
<table class="sign">
    <tr><td>توقيع المحاسب</td><td>ختم الشركة</td></tr>
    <tr><td class="line">&nbsp;</td><td class="line">&nbsp;</td></tr>
</table>
@endif
</body>
</html>
