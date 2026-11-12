@extends('cp.layout')
@section('title', 'الرئيسية')
@section('content')
{{-- الرئيسية بستايل الأدراج (12/11/2026): شريط الصافي وهالشهر، كروت معي/إلي/علي زي كروت الأدراج، وأقسام بنفس شكل الحركات.
     كل الأرصدة والمجاميع bl-money: بتتموّه مع العين. الحركات (آخر الحركات) ما بتتموّه، زي سجل الأدراج. --}}
@php
    $n = fn ($v) => number_format(abs((float) $v), 2).' ₪';
    $sg = fn ($v) => (float) $v < -0.004 ? '−' : '';
    $owedRows = array_filter(['على الزبائن' => $owed['clients'], 'على الأشخاص' => $owed['persons'], 'على الموظفين والموردين' => $owed['vendors']], fn ($v) => $v > 0.005);
    $oweRows = array_filter(['للموظفين' => $owe['workers'], 'للموردين' => $owe['suppliers'], 'للأشخاص' => $owe['persons'], 'للزبائن' => $owe['clients']], fn ($v) => $v > 0.005);
    $max = max(1, collect($flow)->flatMap(fn ($f) => [$f['in'], $f['out']])->max());
    $diff = $month['in'] - $month['out'];
@endphp
<div class="cl-page bl-page db2">
    <div class="cl-top">
        <h1 class="cl-h">أهلاً م. محمد</h1>
        <span class="db2-date">{{ format_date(now()) }}</span>
    </div>

    <div class="bl-card bl-strip db2-strip">
        <div class="bl-stot">
            <span class="bl-name">الصافي</span>
            <b class="bl-money bl-big {{ $net < 0 ? 'due' : '' }}" dir="ltr">{{ $sg($net) }}₪ {{ number_format(abs($net), 2) }}</b>
            <span class="db2-hint">معي + إلي − علي: لو انسدّ كل إشي، هاد اللي بيضل معك</span>
        </div>
        <div class="db2-month">
            <span class="bl-rl">هالشهر · {{ now()->month }}/{{ now()->year }}</span>
            <div>
                <span class="bl-cb"><span class="bl-rl">دخل</span><b class="bl-money in" dir="ltr">₪ {{ number_format($month['in'], 2) }}</b></span>
                <span class="bl-cb"><span class="bl-rl">طلع</span><b class="bl-money due" dir="ltr">₪ {{ number_format($month['out'], 2) }}</b></span>
                <span class="bl-cb"><span class="bl-rl">الفرق</span><b class="bl-money {{ $diff < 0 ? 'due' : '' }}" dir="ltr">{{ $sg($diff) }}₪ {{ number_format(abs($diff), 2) }}</b></span>
            </div>
        </div>
    </div>

    <div class="db2-cards">
        <div class="bl-card">
            <a class="bl-main" href="{{ route('cp.balances.index') }}">
                <span class="bl-name">معي <small>بكل الأدراج، بسعر اليوم</small></span>
                <b class="bl-money {{ $withMe < 0 ? 'due' : '' }}">{{ $sg($withMe) }}{{ $n($withMe) }}</b>
                <span class="bl-curs">
                    @foreach($drawers as $d)
                        <span class="bl-cur {{ abs($d['ils']) < 0.005 ? 'zero' : '' }}"><span>{{ $d['name'] }}</span><b class="bl-money {{ $d['ils'] < 0 ? 'due' : '' }}" dir="ltr">{{ $sg($d['ils']) }}{{ number_format(abs($d['ils']), 2) }}</b></span>
                    @endforeach
                </span>
            </a>
        </div>
        <div class="bl-card">
            <div class="bl-main">
                <span class="bl-name">إلي <small>ناس عليهم رصيد</small></span>
                <b class="bl-money due">{{ $n(array_sum($owed)) }}</b>
                <span class="bl-curs">
                    @forelse($owedRows as $label => $v)
                        <span class="bl-cur"><span>{{ $label }}</span><b class="bl-money" dir="ltr">{{ number_format($v, 2) }}</b></span>
                    @empty
                        <span class="bl-cur zero"><span>ما في حدا عليه إشي</span></span>
                    @endforelse
                </span>
            </div>
        </div>
        <div class="bl-card">
            <div class="bl-main">
                <span class="bl-name">علي <small>ناس إلهم رصيد</small></span>
                <b class="bl-money credit">{{ $n(array_sum($owe)) }}</b>
                <span class="bl-curs">
                    @forelse($oweRows as $label => $v)
                        <span class="bl-cur"><span>{{ $label }}</span><b class="bl-money" dir="ltr">{{ number_format($v, 2) }}</b></span>
                    @empty
                        <span class="bl-cur zero"><span>ما عليك لحدا إشي</span></span>
                    @endforelse
                </span>
            </div>
        </div>
    </div>

    <section class="bl-sec">
        <div class="bl-sh"><div><h2>آخر 6 شهور</h2><small>الدفعات الواردة والصادرة بس، بدون التحويلات بين الأدراج، وبالشيكل.</small></div>
            <div class="db-legend"><span><i class="in"></i>دخل</span><span><i class="out"></i>طلع</span></div></div>
        <div class="db-bars db2-bars" role="img" aria-label="الدخل والطالع لآخر 6 أشهر">
            @foreach($flow as $key => $f)
                <div class="db-bar {{ $key === now()->format('Y-m') ? 'now' : '' }}">
                    <div class="db-bar-pair">
                        <i class="in" style="height: {{ round($f['in'] / $max * 100) }}%"></i>
                        <i class="out" style="height: {{ round($f['out'] / $max * 100) }}%"></i>
                    </div>
                    <small>{{ (int) substr($key, 5) }}/{{ substr($key, 2, 2) }}</small>
                </div>
            @endforeach
        </div>
    </section>

    @if($subs->isNotEmpty())
    <section class="bl-sec" id="dbSubs">
        <div class="bl-sh"><div><h2>اشتراكات {{ now()->month }}/{{ now()->year }} بانتظار التأكيد</h2><small>اكبس على الزبون لتراجع وتأكد.</small></div>
            <a class="bl-tbtn" href="{{ route('cp.subscriptions.confirm') }}">تأكيد الكل</a></div>
        @foreach($subs as $rows)
            @php($c = $rows->first()['client'])
            <a class="bl-lrow db2-row" href="{{ route('cp.clients.show', $c) }}#csSubs">
                <span class="bl-ldesc"><b>{{ $c->directoryLabel() }}</b><small>{{ $rows->pluck('title')->unique()->join('، ') }}</small></span>
                <b class="bl-lamt bl-money" dir="ltr">₪ {{ number_format($rows->sum(fn ($r) => (float) $r['amount']), 2) }}</b>
            </a>
        @endforeach
    </section>
    @endif

    @if(count($topClients) || count($topVendors))
    <section class="bl-sec">
        <div class="bl-sh"><h2>بدها متابعة</h2></div>
        @if(count($topClients))<div class="bl-lday">أكثر زبائن عليهم رصيد</div>@endif
        @foreach($topClients as $r)
            <a class="bl-lrow db2-row" href="{{ $r['url'] }}"><span class="bl-ldesc"><b>{{ $r['name'] }}</b><small>زبون</small></span><b class="bl-lamt bl-money due" dir="ltr">₪ {{ number_format(abs($r['amount']), 2) }}</b></a>
        @endforeach
        @if(count($topVendors))<div class="bl-lday">أكثر موردين وموظفين إلهم</div>@endif
        @foreach($topVendors as $r)
            <a class="bl-lrow db2-row" href="{{ $r['url'] }}"><span class="bl-ldesc"><b>{{ $r['name'] }}</b><small>{{ $r['type'] }}</small></span><b class="bl-lamt bl-money in" dir="ltr">₪ {{ number_format(abs($r['amount']), 2) }}</b></a>
        @endforeach
    </section>
    @endif

    <section class="bl-sec">
        <div class="bl-sh"><div><h2>آخر الحركات</h2><small>من كل الأقسام</small></div><a class="bl-tbtn" href="{{ route('cp.ledger.index') }}">كل الحركات ‹</a></div>
        @php($day = null)
        @forelse($recent as $e)
            @php($dl = day_label($e->occurred_on))
            @if($dl !== $day)<div class="bl-lday">{{ $dl }}</div>@php($day = $dl)@endif
            @php($neg = \App\Support\Money::isNegative($e->amount))
            <div class="bl-lrow db2-row">
                <span class="bl-ldesc">{{ \App\Support\LedgerText::for($e) }}<small>{{ $e->paymentMethod?->name }}</small></span>
                <b class="bl-lamt {{ $neg ? '' : 'in' }}" dir="ltr">{{ $neg ? '−' : '+' }}{{ $e->currency->format(\App\Support\Money::abs($e->amount)) }}</b>
            </div>
        @empty
            <p class="bl-open">ما في حركات لسا.</p>
        @endforelse
    </section>
</div>
@endsection
