@extends('cp.layout')
@section('title', $method->name)
@section('content')
@php
    $tabs = $funds->mapWithKeys(fn ($f) => [$f->slug => $f->slug === 'family' ? 'شخصي' : ($f->slug === 'business' ? 'عمل' : $f->name)])->all() + ['all' => 'الكل'];
    $q = fn (array $over) => route('cp.balances.show', array_filter(array_merge(['method' => $method->id, 'cur' => $cur?->code, 'from' => $from, 'to' => $to], $over), fn ($v) => $v !== null && $v !== ''));
    $fmt = fn ($c, $v) => ((float) $v < 0 ? '−' : '').$c->format(abs((float) $v));
@endphp
<div class="cl-page bl-page">
    <div class="cl-top">
        <a href="{{ route('cp.balances.index') }}" class="cl-ghost" aria-label="رجوع"><span class="material-symbols-outlined">arrow_forward</span></a>
        <h1 class="cl-h">{{ $method->name }}</h1>
    </div>

    <div class="bl-grid bl-curcards">
        @foreach($cards as $card)
            <div class="bl-card {{ abs($card['amount']) < 0.005 ? 'zero' : '' }}"><div class="bl-main"><span class="bl-name">{{ \App\Support\DrawerPlaces::curName($card['currency']->code, $card['currency']->name) }} {{ $card['currency']->symbol }}</span><b class="bl-money {{ $card['amount'] < 0 ? 'due' : '' }}" dir="ltr">{{ ($card['amount'] < 0 ? '−' : '').number_format(abs($card['amount']), 2) }}</b></div></div>
        @endforeach
    </div>

    @php
        $placed = [];
        foreach ($places as $p) { foreach ($p['amounts'] as $code => $v) { $placed[$code] = ($placed[$code] ?? 0) + $v; } }
        $num = fn ($v) => abs((float) $v) < 0.005 ? '0.00' : (($v < 0 ? '−' : '').number_format(abs((float) $v), 2));
    @endphp
    <section class="bl-places">
        <div class="bl-places-top">
            <div><h2>الأماكن</h2><small>للعرض بس، ما بيأثر على الأرصدة</small></div>
            <button type="button" class="bl-outline" id="plEdit">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 15]) {{ count($places) ? 'تعديل التوزيع' : 'وزّع على أماكن' }}</button>
        </div>
        @if(count($places))
            <div class="bl-prow bl-phead"><span>المكان</span>@foreach($cards as $card)<span>{{ \App\Support\DrawerPlaces::curName($card['currency']->code, $card['currency']->name) }} {{ $card['currency']->symbol }}</span>@endforeach</div>
            @foreach($places as $p)
                <div class="bl-prow"><span>{{ $p['name'] }}</span>@foreach($cards as $card)@php($v = $p['amounts'][$card['currency']->code] ?? 0)<b class="bl-money {{ $v ? '' : 'zero' }}" dir="ltr">{{ $num($v) }}</b>@endforeach</div>
            @endforeach
            <div class="bl-prow bl-pfree"><span>غير موزّع</span>@foreach($cards as $card)@php($free = round($card['amount'] - ($placed[$card['currency']->code] ?? 0), 2))<b class="bl-money {{ abs($free) < 0.005 ? 'zero' : 'due' }}" dir="ltr">{{ $num($free) }}</b>@endforeach</div>
        @endif
    </section>

    <div class="cl-sheet" id="plSheet" hidden>
        <form class="cl-dialog bl-pdialog" method="post" action="{{ route('cp.balances.places', ['method' => $method->id]) }}" role="dialog" aria-modal="true" aria-labelledby="plT">
            @csrf
            <h2 id="plT">توزيع {{ $method->name }} على الأماكن</h2>
            <p class="bl-pnote">للعرض بس، ما بيأثر على الحركات ولا الأرصدة.</p>
            <div class="bl-pgrid" id="plGrid">
                <div class="bl-pg bl-pgh"><span>المكان</span>@foreach($cards as $card)<span>{{ \App\Support\DrawerPlaces::curName($card['currency']->code, $card['currency']->name) }}</span>@endforeach<span></span></div>
            </div>
            <button type="button" class="bl-padd" id="plAdd">+ مكان جديد</button>
            <div class="bl-pgrid bl-psum">
                <div class="bl-pg"><span>رصيد الدرج</span>@foreach($cards as $card)<b dir="ltr">{{ $num($card['amount']) }}</b>@endforeach<span></span></div>
                <div class="bl-pg bl-pfree"><span>غير موزّع</span>@foreach($cards as $card)<b dir="ltr" data-free="{{ $card['currency']->code }}"></b>@endforeach<span></span></div>
            </div>
            <small class="bl-perr" id="plErr" hidden>الموزّع أكتر من رصيد الدرج. صلّح الرقم الأحمر.</small>
            <div class="cl-actions">
                <button type="submit" class="cp-btn cp-btn-primary" id="plSave">حفظ</button>
                <button type="button" class="cp-btn cp-btn-ghost" id="plCancel">إلغاء</button>
            </div>
        </form>
    </div>

    <div class="cl-tools">
        <div class="cl-seg">
            <a href="{{ $q(['cur' => null]) }}" class="{{ $cur ? '' : 'on' }}">كل العملات</a>
            @foreach($currencies as $c)<a href="{{ $q(['cur' => $c->code]) }}" class="{{ $cur?->id === $c->id ? 'on' : '' }}">{{ $c->code }}</a>@endforeach
        </div>
        @include('cp.partials.period-filter')
    </div>

    @if($from && count($opening))
        <p class="cs-sum">رصيد سابق: @foreach($opening as $cid => $v)<span dir="ltr">{{ $fmt($currencies->firstWhere('id', $cid), $v) }}</span>@if(!$loop->last)، @endif @endforeach</p>
    @endif

    <div class="cl-list bl-ledger">
        <div class="bl-row bl-head"><span>التاريخ</span><span>البيان</span><span>المبلغ</span><span>الرصيد بعدها</span></div>
        @forelse($rows as $r)
            @php($e = $r['entry'])
            <div class="bl-row">
                <span class="bl-date">{{ format_date($e->occurred_on) }}</span>
                <span class="bl-desc">{{ $e->description }}@if($e->notes)<small>{{ $e->notes }}</small>@endif</span>
                <span class="bl-amt {{ (float) $e->amount < 0 ? 'due' : 'credit' }}" dir="ltr">{{ $fmt($e->currency, $e->amount) }}</span>
                <span class="bl-after {{ (float) $r['after'] < 0 ? 'due' : '' }}" dir="ltr">{{ $fmt($e->currency, $r['after']) }}</span>
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
    $('csPresets').innerHTML = P.map((p, i) => `<button type="button" data-i="${i}" class="${(p === hit || (!from && !to && i === 0)) ? 'on' : ''}">${p[0]}</button>`).join('');
    $('csPresets').addEventListener('click', (e) => { const b = e.target.closest('[data-i]'); if (b) go(P[b.dataset.i][1], P[b.dataset.i][2]); });
    $('csRangeBtn').addEventListener('click', (e) => { e.stopPropagation(); $('csRangeMenu').hidden = !$('csRangeMenu').hidden; });
    document.addEventListener('click', (e) => { if (!e.target.closest('.cs-range')) $('csRangeMenu').hidden = true; });
    if (from && !hit) { $('csFrom').value = cpPeriod.label(from); $('csTo').value = cpPeriod.label(to || ''); }
    $('csApply').addEventListener('click', () => { let f = cpPeriod.iso($('csFrom')), t = cpPeriod.iso($('csTo')); if (f && t && f > t) [f, t] = [t, f]; go(f, t); });
})();
</script>
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const codes = @json(collect($cards)->map(fn ($c) => $c['currency']->code)->values());
    const have = @json(collect($cards)->mapWithKeys(fn ($c) => [$c['currency']->code => $c['amount']]));
    const saved = @json($places);
    const grid = $('plGrid');
    const fmt = (v) => (v < 0 ? '−' : '') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (s) => { const v = parseFloat(String(s || '').replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/,/g, '')); return isFinite(v) ? v : 0; };
    let n = 0;
    function row(p) {
        const i = n++;
        const d = document.createElement('div');
        d.className = 'bl-pg';
        d.innerHTML = '<input name="places[' + i + '][name]" placeholder="الخزنة، المكتب..." maxlength="60" aria-label="اسم المكان">'
            + codes.map((c) => '<input name="places[' + i + '][amounts][' + c + ']" inputmode="decimal" dir="ltr" placeholder="0" data-cur="' + c + '" aria-label="' + c + '">').join('')
            + '<button type="button" class="bl-pdel" aria-label="حذف المكان">×</button>';
        d.querySelector('input').value = p ? p.name : '';
        if (p) codes.forEach((c) => { if (p.amounts && p.amounts[c]) d.querySelector('[data-cur="' + c + '"]').value = p.amounts[c]; });
        grid.appendChild(d);
        return d;
    }
    function calc() {
        let bad = false;
        codes.forEach((c) => {
            let sum = 0;
            grid.querySelectorAll('[data-cur="' + c + '"]').forEach((el) => { sum += num(el.value); });
            const free = Math.round((have[c] - sum) * 100) / 100;
            const over = sum > Math.max(0, have[c]) + 0.004;
            grid.querySelectorAll('[data-cur="' + c + '"]').forEach((el) => el.classList.toggle('bad', over && num(el.value) > 0));
            const out = document.querySelector('[data-free="' + c + '"]');
            out.textContent = fmt(free); out.className = Math.abs(free) < 0.005 ? 'zero' : 'due';
            bad = bad || over;
        });
        $('plErr').hidden = !bad; $('plSave').disabled = bad;
    }
    function open() {
        grid.querySelectorAll('.bl-pg:not(.bl-pgh)').forEach((r) => r.remove()); n = 0;
        (saved.length ? saved : [null]).forEach(row);
        calc(); $('plSheet').hidden = false;
        setTimeout(() => grid.querySelector('input').focus(), 30);
    }
    $('plEdit').addEventListener('click', open);
    $('plAdd').addEventListener('click', () => { row(null).querySelector('input').focus(); calc(); });
    grid.addEventListener('input', calc);
    grid.addEventListener('click', (e) => { const b = e.target.closest('.bl-pdel'); if (b) { b.parentElement.remove(); calc(); } });
    $('plCancel').addEventListener('click', () => { $('plSheet').hidden = true; });
    $('plSheet').addEventListener('click', (e) => { if (e.target.id === 'plSheet') $('plSheet').hidden = true; });
    $('plSheet').querySelector('form').addEventListener('submit', () => {
        grid.querySelectorAll('[data-cur]').forEach((el) => { el.value = String(num(el.value) || ''); });
    });
})();
</script>
@endsection
