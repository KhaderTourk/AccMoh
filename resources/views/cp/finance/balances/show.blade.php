@extends('cp.layout')
@section('title', $method->name)
@section('content')
@php
    $tabs = $funds->mapWithKeys(fn ($f) => [$f->slug => $f->slug === 'family' ? 'شخصي' : ($f->slug === 'business' ? 'عمل' : $f->name)])->all() + ['all' => 'الكل'];
    $q = fn (array $over) => route('cp.balances.show', array_filter(array_merge(['method' => $method->id, 'tab' => $tab, 'cur' => $cur?->code, 'from' => $from, 'to' => $to], $over), fn ($v) => $v !== null && $v !== ''));
    $fmt = fn ($c, $v) => ((float) $v < 0 ? '−' : '').$c->format(abs((float) $v));
@endphp
<div class="cl-page bl-page">
    <div class="cl-top">
        <a href="{{ route('cp.balances.index', ['tab' => $tab]) }}" class="cl-ghost" aria-label="رجوع"><span class="material-symbols-outlined">arrow_forward</span></a>
        <h1 class="cl-h">{{ $method->name }}</h1>
    </div>

    <div class="bl-grid bl-curcards">
        @forelse($cards as $card)
            <div class="bl-card"><div class="bl-main"><span class="bl-name">{{ $card['currency']->name }}</span><b class="{{ $card['amount'] < 0 ? 'due' : '' }}">{{ $fmt($card['currency'], $card['amount']) }}</b></div></div>
        @empty
            <div class="bl-card"><div class="bl-main"><b class="bl-empty">فاضي</b></div></div>
        @endforelse
    </div>

    <div class="cl-tools">
        <div class="cl-seg">
            @foreach($tabs as $key => $label)<a href="{{ $q(['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }}</a>@endforeach
        </div>
        <div class="cl-seg">
            <a href="{{ $q(['cur' => null]) }}" class="{{ $cur ? '' : 'on' }}">كل العملات</a>
            @foreach($currencies as $c)<a href="{{ $q(['cur' => $c->code]) }}" class="{{ $cur?->id === $c->id ? 'on' : '' }}">{{ $c->code }}</a>@endforeach
        </div>
        @include('cp.partials.period-filter')
    </div>

    @if($from && count($opening))
        <p class="cs-sum">رصيد سابق: @foreach($opening as $cid => $v){{ $fmt($currencies->firstWhere('id', $cid), $v) }}@if(!$loop->last)، @endif @endforeach</p>
    @endif

    <div class="cl-list bl-ledger">
        <div class="bl-row bl-head"><span>التاريخ</span><span>البيان</span><span>المبلغ</span><span>الرصيد بعدها</span></div>
        @forelse($rows as $r)
            @php($e = $r['entry'])
            <div class="bl-row">
                <span class="bl-date">{{ format_date($e->occurred_on) }}</span>
                <span class="bl-desc">{{ $e->description }}@if($tab === 'all') <small>{{ $tabs[$e->fund->slug] ?? '' }}</small>@endif @if($e->notes)<small>{{ $e->notes }}</small>@endif</span>
                <span class="{{ (float) $e->amount < 0 ? 'due' : 'credit' }}">{{ $fmt($e->currency, $e->amount) }}</span>
                <span class="{{ (float) $r['after'] < 0 ? 'due' : '' }}">{{ $fmt($e->currency, $r['after']) }}</span>
            </div>
        @empty
            <p class="cl-empty">ما في حركات بهالفترة.</p>
        @endforelse
    </div>
</div>
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const today = @json(now()->toDateString());
    const from = @json($from), to = @json($to);
    const base = @json($q(['from' => null, 'to' => null]));
    const iso = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const now = new Date(today + 'T00:00:00'), y = now.getFullYear(), m = now.getMonth();
    const P = [['كل الوقت', null, null], ['هالشهر', iso(new Date(y, m, 1)), iso(new Date(y, m + 1, 0))], ['الشهر الماضي', iso(new Date(y, m - 1, 1)), iso(new Date(y, m, 0))],
        ['آخر 3 شهور', iso(new Date(y, m - 2, 1)), iso(new Date(y, m + 1, 0))], ['هالسنة', iso(new Date(y, 0, 1)), iso(new Date(y, 11, 31))], ['السنة الماضية', iso(new Date(y - 1, 0, 1)), iso(new Date(y - 1, 11, 31))]];
    const go = (f, t) => { const u = new URL(base, location.href); if (f) u.searchParams.set('from', f); if (t) u.searchParams.set('to', t); location.href = u.toString(); };
    const hit = P.find((p) => p[1] === from && p[2] === to);
    $('csRangeTxt').textContent = !from && !to ? 'كل الوقت' : (hit ? hit[0] : cpPeriod.label(from || '') + ' – ' + cpPeriod.label(to || today));
    $('csRangeBtn').classList.toggle('on', !!(from || to));
    $('csPresets').innerHTML = P.map((p, i) => `<button type="button" data-i="${i}">${p[0]}</button>`).join('');
    $('csPresets').addEventListener('click', (e) => { const b = e.target.closest('[data-i]'); if (b) go(P[b.dataset.i][1], P[b.dataset.i][2]); });
    $('csRangeBtn').addEventListener('click', (e) => { e.stopPropagation(); $('csRangeMenu').hidden = !$('csRangeMenu').hidden; });
    document.addEventListener('click', (e) => { if (!e.target.closest('.cs-range')) $('csRangeMenu').hidden = true; });
    if (from && !hit) { $('csFrom').value = cpPeriod.label(from); $('csTo').value = cpPeriod.label(to || ''); }
    $('csApply').addEventListener('click', () => { let f = cpPeriod.iso($('csFrom')), t = cpPeriod.iso($('csTo')); if (f && t && f > t) [f, t] = [t, f]; go(f, t); });
})();
</script>
@endsection
