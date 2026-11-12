@extends('cp.layout')
@section('title', 'الرئيسية')
@section('content')
@php
    $n = fn ($v) => number_format(abs((float) $v), 2).' ₪';
    $owedRows = array_filter(['على الزبائن' => $owed['clients'], 'على الأشخاص' => $owed['persons'], 'على الموظفين والموردين' => $owed['vendors']], fn ($v) => $v > 0.005);
    $oweRows = array_filter(['للموظفين' => $owe['workers'], 'للموردين' => $owe['suppliers'], 'للأشخاص' => $owe['persons'], 'للزبائن' => $owe['clients']], fn ($v) => $v > 0.005);
    $max = max(1, collect($flow)->flatMap(fn ($f) => [$f['in'], $f['out']])->max());
@endphp
<div class="db-page">
    <div class="db-hello"><h1>أهلاً م. محمد</h1><span>{{ format_date(now()) }}</span></div>
    <div class="db-cards">
        <a class="db-card" href="{{ route('cp.balances.index') }}">
            <span class="db-k">معي</span>
            <b class="db-v bl-money">{{ $n($withMe) }}</b>
            <small>بكل الأدراج، بسعر اليوم · الأدراج والأرصدة ‹</small>
        </a>
        <div class="db-card">
            <span class="db-k">إلي</span>
            <b class="db-v due">{{ $n(array_sum($owed)) }}</b>
            @foreach($owedRows as $label => $v)<div class="db-line"><span>{{ $label }}</span><b>{{ $n($v) }}</b></div>@endforeach
        </div>
        <div class="db-card">
            <span class="db-k">علي</span>
            <b class="db-v credit">{{ $n(array_sum($owe)) }}</b>
            @foreach($oweRows as $label => $v)<div class="db-line"><span>{{ $label }}</span><b>{{ $n($v) }}</b></div>@endforeach
        </div>
    </div>

    <div class="db-net">
        <span>الصافي</span>
        <b class="bl-money {{ $net < 0 ? 'due' : '' }}">{{ $net < 0 ? '−' : '' }}{{ $n($net) }}</b>
        <small>معي + إلي − علي: لو انسدّ كل إشي، هاد اللي بيضل معك</small>
    </div>

    <section class="db-panel">
        <h2>هالشهر <small>{{ now()->month }}/{{ now()->year }}</small></h2>
        <div class="db-month">
            <div><span>دخل</span><b class="credit">{{ $n($month['in']) }}</b></div>
            <div><span>طلع</span><b class="due">{{ $n($month['out']) }}</b></div>
            <div><span>الفرق</span><b>{{ $month['in'] - $month['out'] < 0 ? '−' : '' }}{{ $n($month['in'] - $month['out']) }}</b></div>
        </div>
        <div class="db-bars" role="img" aria-label="الدخل والطالع لآخر 6 أشهر">
            @foreach($flow as $key => $f)
                <div class="db-bar">
                    <div class="db-bar-pair">
                        <i class="in" style="height: {{ round($f['in'] / $max * 100) }}%" title="دخل {{ $n($f['in']) }}"></i>
                        <i class="out" style="height: {{ round($f['out'] / $max * 100) }}%" title="طلع {{ $n($f['out']) }}"></i>
                    </div>
                    <small>{{ (int) substr($key, 5) }}/{{ substr($key, 2, 2) }}</small>
                </div>
            @endforeach
        </div>
        <div class="db-legend"><span><i class="in"></i>دخل</span><span><i class="out"></i>طلع</span></div>
        <p class="db-hint">الدفعات الواردة والصادرة بس، بدون التحويلات بين الأدراج، وبالشيكل.</p>
    </section>

    @if($subs->isNotEmpty())
    <section class="db-panel" id="dbSubs">
        <h2>اشتراكات شهر {{ now()->month }}/{{ now()->year }} بانتظار التأكيد <a href="{{ route('cp.subscriptions.confirm') }}">تأكيد الكل</a></h2>
        <p class="db-hint">كل الزباين بمكان واحد. اكبس على الزبون لتراجع وتأكد.</p>
        @foreach($subs as $rows)
            @php($c = $rows->first()['client'])
            <a class="db-row" href="{{ route('cp.clients.show', $c) }}#csSubs">
                <span>{{ $c->directoryLabel() }} <small>{{ $rows->pluck('title')->unique()->join('، ') }}</small></span>
                <b>{{ $n($rows->sum(fn ($r) => (float) $r['amount'])) }}</b>
            </a>
        @endforeach
    </section>
    @endif

    @if(count($topClients) || count($topVendors))
    <section class="db-panel">
        <h2>بدها متابعة</h2>
        @if(count($topClients))<h3 class="db-sub">أكثر زبائن عليهم رصيد</h3>@endif
        @foreach($topClients as $r)
            <a class="db-row" href="{{ $r['url'] }}"><span>{{ $r['name'] }} <small>زبون</small></span><b class="due">{{ $n($r['amount']) }}</b></a>
        @endforeach
        @if(count($topVendors))<h3 class="db-sub">أكثر موردين وموظفين إلهم</h3>@endif
        @foreach($topVendors as $r)
            <a class="db-row" href="{{ $r['url'] }}"><span>{{ $r['name'] }} <small>{{ $r['type'] }}</small></span><b class="credit">{{ $n($r['amount']) }}</b></a>
        @endforeach
    </section>
    @endif

    <section class="db-panel">
        <h2>آخر الحركات <small>من كل الأقسام</small></h2>
        @forelse($recent as $e)
            <div class="db-row">
                <span>{{ $e->description }} <small>{{ $e->paymentMethod?->name }} · {{ format_date($e->occurred_on) }}</small></span>
                <b class="{{ \App\Support\Money::isNegative($e->amount) ? 'due' : 'credit' }}">{{ \App\Support\Money::isNegative($e->amount) ? '−' : '' }}{{ $e->currency->format(\App\Support\Money::abs($e->amount)) }}</b>
            </div>
        @empty
            <p class="db-hint">ما في حركات لسا.</p>
        @endforelse
    </section>
</div>
@endsection
