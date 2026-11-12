@extends('cp.layout')
@section('title', $method->name)
@section('content')
@php
    $tabs = $funds->mapWithKeys(fn ($f) => [$f->slug => $f->slug === 'family' ? 'شخصي' : ($f->slug === 'business' ? 'عمل' : $f->name)])->all() + ['all' => 'الكل'];
    $q = fn (array $over) => route('cp.balances.show', array_filter(array_merge(['method' => $method->id, 'cur' => $cur?->code, 'from' => $from, 'to' => $to], $over), fn ($v) => $v !== null && $v !== ''));
    $fmt = fn ($c, $v) => ((float) $v < 0 ? '−' : '').$c->format(abs((float) $v));
@endphp
@php
    // العملات اللي إلها عمود بالأماكن: اللي فيها رصيد بالدرج أو بمكان (الدينار الصفر ما بيطلع).
    $used = collect($cards)->filter(fn ($c) => abs($c['amount']) >= 0.005 || collect($places)->contains(fn ($p) => ($p['amounts'][$c['currency']->code] ?? 0) > 0))->values();
    if ($used->isEmpty()) { $used = collect($cards)->take(1); }
    $drawerIls = collect($cards)->sum(fn ($c) => \App\Support\Rates::toIls((float) $c['amount'], $c['currency']->code));
    $placed = [];
    foreach ($places as $p) { foreach ($p['amounts'] as $code => $v) { $placed[$code] = ($placed[$code] ?? 0) + $v; } }
    $num = fn ($v) => abs((float) $v) < 0.005 ? '0.00' : (($v < 0 ? '−' : '').number_format(abs((float) $v), 2));
    $ilsOf = function (array $amounts) { $t = 0.0; foreach ($amounts as $code => $v) { $t += \App\Support\Rates::toIls((float) $v, $code); } return $t; };
    $pct = fn ($v) => $drawerIls > 0 ? max(0, min(100, round($v / $drawerIls * 100))) : 0;
    $free = collect($cards)->mapWithKeys(fn ($c) => [$c['currency']->code => round($c['amount'] - ($placed[$c['currency']->code] ?? 0), 2)])->all();
    $sym = collect($cards)->mapWithKeys(fn ($c) => [$c['currency']->code => $c['currency']->symbol])->all();
    // ألوان الأماكن بدون لون الهوية (وردي) وبدون أخضر الأرصدة (12/11/2026).
    $palette = ['#4C8DFF', '#7C5CFF', '#F5A524', '#22B8CF', '#94A3B8', '#C084FC'];
    $dayLabel = function ($d) {
        $t = now()->startOfDay();
        $txt = $d->format('j/n/Y');

        return $d->isSameDay($t) ? 'اليوم · '.$txt : ($d->isSameDay($t->copy()->subDay()) ? 'مبارح · '.$txt : $txt);
    };
@endphp
<div class="cl-page bl-page bl-dpage">
    {{-- صفحة الدرج (12/11/2026، صور inner-1/inner-2 المعتمدة): نفس شريط الملخص، «تحويل من الدرج»، و«⋯» فيها تسوية رصيد. --}}
    <div class="cl-top">
        <a href="{{ route('cp.balances.index') }}" class="cl-ghost bl-backbtn" aria-label="رجوع">@include('cp.partials.bs-icon', ['name' => 'back', 'size' => 18])</a>
        <h1 class="cl-h">{{ $method->name }}</h1>
        <div class="cl-sp"></div>
        <div class="bl-hmore">
            <button type="button" class="bl-hdots" id="blDots" aria-label="خيارات {{ $method->name }}" aria-haspopup="menu">⋯</button>
            <div class="cl-menu" id="blDotsMenu" hidden>
                <button type="button" data-settle="{{ $method->id }}" data-name="{{ $method->name }}">تسوية رصيد</button>
            </div>
        </div>
        @include('cp.partials.transfer-wizard', ['wizard' => $wizard, 'wizardFrom' => ['id' => $method->id, 'name' => $method->name]])
    </div>

    <div class="bl-card bl-strip">
        <div class="bl-stot">
            <span class="bl-name">رصيد الدرج بسعر اليوم</span>
            <b class="bl-money bl-big {{ $drawerIls < 0 ? 'due' : '' }}" dir="ltr">{{ $drawerIls < 0 ? '−' : '' }}₪ {{ number_format(abs($drawerIls), 2) }}</b>
        </div>
        <div class="bl-rates">
            @foreach($cards as $card)
                <div class="bl-rx bl-cb {{ abs($card['amount']) < 0.005 ? 'zero' : '' }}">
                    <span class="bl-rl">{{ \App\Support\DrawerPlaces::curName($card['currency']->code, $card['currency']->name) }} {{ $card['currency']->symbol }}</span>
                    <b class="bl-money {{ $card['amount'] < 0 ? 'due' : '' }}" dir="ltr">{{ $num($card['amount']) }}</b>
                </div>
            @endforeach
        </div>
    </div>

    <section class="bl-sec">
        <div class="bl-sh">
            <div><h2>الأماكن</h2><small>للعرض بس، ما بيأثّر على الأرصدة</small></div>
            <button type="button" class="bl-tbtn" id="plEdit">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 14]) {{ count($places) ? 'تعديل التوزيع' : 'وزّع على أماكن' }}</button>
        </div>
        @if(count($places))
            @php
                $freeIls = $ilsOf(array_filter($free, fn ($v) => $v > 0));
            @endphp
            <div class="bl-stk">
                @foreach($places as $i => $p)<i style="width: {{ $pct($ilsOf($p['amounts'])) }}%; background: {{ $palette[$i % count($palette)] }}"></i>@endforeach
                <i style="width: {{ $pct($freeIls) }}%" class="free"></i>
            </div>
            @foreach($places as $i => $p)
                <div class="bl-pl">
                    <span class="bl-pn"><i style="background: {{ $palette[$i % count($palette)] }}"></i>{{ $p['name'] }}</span>
                    <span class="bl-pa">@foreach($used as $card)@php $v = $p['amounts'][$card['currency']->code] ?? 0; @endphp @if($v > 0)<b class="bl-money" dir="ltr">{{ $card['currency']->symbol }} {{ $num($v) }}</b>@endif @endforeach</span>
                    <span class="bl-pp" dir="ltr">{{ $pct($ilsOf($p['amounts'])) }}%</span>
                </div>
            @endforeach
            <div class="bl-pl free">
                <span class="bl-pn"><i></i>غير موزّع</span>
                <span class="bl-pa">
                    @php $anyFree = false; @endphp
                    @foreach($used as $card)@php $v = $free[$card['currency']->code] ?? 0; @endphp @if(abs($v) >= 0.005)@php $anyFree = true; @endphp<b class="bl-money {{ $v < 0 ? 'due' : '' }}" dir="ltr">{{ $card['currency']->symbol }} {{ $num($v) }}</b>@endif @endforeach
                    @if(! $anyFree)<b class="bl-ok">✓ كله موزّع</b>@endif
                </span>
                <span class="bl-pp" dir="ltr">{{ $pct($freeIls) }}%</span>
            </div>
        @endif
    </section>

    <div class="cl-sheet tw-sheet" id="plSheet" hidden>
        <form class="tw-dialog bl-pdialog" method="post" action="{{ route('cp.balances.places', ['method' => $method->id]) }}" role="dialog" aria-modal="true" aria-labelledby="plT">
            @csrf
            <div class="tw-head">
                <h2 id="plT">توزيع {{ $method->name }} على الأماكن</h2>
                <button type="button" class="tw-x" id="plX" aria-label="إغلاق">@include('cp.partials.bs-icon', ['name' => 'close', 'size' => 18])</button>
            </div>
            <div class="tw-body">
                <p class="bl-pnote">للعرض بس، ما بيأثّر على الحركات ولا الأرصدة.</p>
                <div class="bl-pgrid" id="plGrid" style="--n: {{ $used->count() }}">
                    <div class="bl-pg bl-pgh"><span>المكان</span>@foreach($used as $card)<span>{{ \App\Support\DrawerPlaces::curName($card['currency']->code, $card['currency']->name) }}</span>@endforeach<span></span><span></span></div>
                </div>
                <button type="button" class="bl-padd" id="plAdd">+ مكان جديد</button>
                <div class="bl-pgrid bl-psum" style="--n: {{ $used->count() }}">
                    <div class="bl-pg"><span>رصيد الدرج</span>@foreach($used as $card)<b dir="ltr">{{ $num($card['amount']) }}</b>@endforeach<span></span><span></span></div>
                    <div class="bl-pg bl-pfree"><span>غير موزّع</span>@foreach($used as $card)<b dir="ltr" data-free="{{ $card['currency']->code }}"></b>@endforeach<span></span><span></span></div>
                </div>
                <small class="bl-perr" id="plErr" hidden>الموزّع أكتر من رصيد الدرج. صلّح الرقم الأحمر.</small>
                @error('places')<small class="bl-perr bl-perr-srv">{{ $message }}</small>@enderror
            </div>
            <div class="tw-foot">
                <button type="button" class="tw-back" id="plCancel">إلغاء</button>
                <button type="submit" class="tw-pri" id="plSave">حفظ</button>
            </div>
        </form>
    </div>

    <section class="bl-sec bl-led">
        <div class="bl-sh bl-ledh">
            <h2>الحركات</h2>
            <div class="bl-ledf">
                <div class="bl-seg2" data-soft="cur">
                    <a href="{{ $q(['cur' => null]) }}" class="{{ $cur ? '' : 'on' }}">الكل</a>
                    @foreach($currencies as $c)<a href="{{ $q(['cur' => $c->code]) }}" class="{{ $cur?->id === $c->id ? 'on' : '' }}">{{ \App\Support\DrawerPlaces::curName($c->code, $c->name) }}</a>@endforeach
                </div>
                @include('cp.partials.period-filter')
            </div>
        </div>

        <div data-soft="rows">
        @if($from && count($opening))
            <p class="bl-open">رصيد سابق: @foreach($opening as $cid => $v)<span dir="ltr">{{ $fmt($currencies->firstWhere('id', $cid), $v) }}</span>@if(!$loop->last)، @endif @endforeach</p>
        @endif

        @if(count($rows))
            <div class="bl-lrow bl-lhead"><span>البيان</span><span>المبلغ</span><span>الرصيد بعدها</span></div>
            @php $lastDay = null; @endphp
            @foreach($rows as $r)
                @php
                    $e = $r['entry'];
                    $day = $e->occurred_on->toDateString();
                    $in = (float) $e->amount > 0;
                @endphp
                @if($day !== $lastDay)
                    <div class="bl-lday">{{ $dayLabel($e->occurred_on) }}</div>
                    @php $lastDay = $day; @endphp
                @endif
                <div class="bl-lrow">
                    <span class="bl-ldesc">{{ $r['text'] }}@if($e->notes)<small>{{ $e->notes }}</small>@endif</span>
                    <span class="bl-lamt {{ $in ? 'in' : '' }}" dir="ltr">{{ $in ? '+' : '−' }}{{ $e->currency->format(abs((float) $e->amount)) }}</span>
                    <span class="bl-lafter {{ (float) $r['after'] < 0 ? 'due' : '' }}"><em>بعدها </em><span dir="ltr">{{ $fmt($e->currency, $r['after']) }}</span></span>
                </div>
            @endforeach
        @else
            <p class="cl-empty">ما في حركات بهالفترة.</p>
        @endif
        </div>
    </section>
</div>

@include('cp.partials.settle-sheet')

@include('cp.partials.period-filter-go', ['base' => $q(['from' => null, 'to' => null])])
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const codes = @json($used->map(fn ($c) => $c['currency']->code)->values());
    const SYM = @json($sym);
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
        d.innerHTML = '<input name="places[' + i + '][name]" placeholder="اسم المكان *" maxlength="60" aria-label="اسم المكان">'
            + codes.map((c) => '<label class="bl-pin" dir="ltr"><i>' + (SYM[c] || c) + '</i><input name="places[' + i + '][amounts][' + c + ']" inputmode="decimal" dir="ltr" placeholder="0" data-cur="' + c + '" aria-label="' + c + '"></label>').join('')
            + '<button type="button" class="bl-prest" title="عبّي هالمكان باللي لسا مش موزّع">الباقي</button>'
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
            if (Math.abs(free) < 0.005) { out.textContent = '✓ كله'; out.className = 'ok'; } else { out.textContent = fmt(free); out.className = free < 0 ? 'due' : ''; }
            bad = bad || over;
        });
        // اسم المكان إجباري (12/11/2026): سطر فيه مبلغ بدون اسم بيتلوّن وما بينحفظ.
        let noName = false;
        grid.querySelectorAll('.bl-pg:not(.bl-pgh)').forEach((r) => {
            const nm = r.querySelector('input'), has = [...r.querySelectorAll('[data-cur]')].some((el) => num(el.value) > 0);
            const miss = has && !nm.value.trim();
            nm.classList.toggle('bad', miss); noName = noName || miss;
        });
        // زر «الباقي» بكل سطر (12/11/2026): بيبيّن بس لما يكون في شي غير موزّع.
        const anyRest = codes.some((c) => restOf(c) > 0.004);
        grid.querySelectorAll('.bl-prest').forEach((b) => { b.hidden = !anyRest; });
        $('plErr').textContent = noName ? 'اكتب اسم المكان.' : 'الموزّع أكتر من رصيد الدرج. صلّح الرقم الأحمر.';
        $('plErr').hidden = !(bad || noName); $('plSave').disabled = bad || noName;
    }
    function open() {
        grid.querySelectorAll('.bl-pg:not(.bl-pgh)').forEach((r) => r.remove()); n = 0;
        (saved.length ? saved : [null]).forEach(row);
        calc(); $('plSheet').hidden = false;
        setTimeout(() => grid.querySelector('input').focus(), 30);
    }
    $('plEdit').addEventListener('click', open);
    @if($errors->has('places')) open(); @endif
    $('plAdd').addEventListener('click', () => { row(null).querySelector('input').focus(); calc(); });
    function restOf(c) { let sum = 0; grid.querySelectorAll('[data-cur="' + c + '"]').forEach((el) => { sum += num(el.value); }); return Math.round((Math.max(0, have[c]) - sum) * 100) / 100; }

    grid.addEventListener('input', calc);
    grid.addEventListener('click', (e) => {
        const b = e.target.closest('.bl-pdel'); if (b) { b.parentElement.remove(); calc(); return; }
        const r = e.target.closest('.bl-prest');
        if (r) { const pg = r.parentElement; codes.forEach((c) => { const rest = restOf(c); if (rest > 0.004) { const el = pg.querySelector('[data-cur="' + c + '"]'); el.value = String(Math.round((num(el.value) + rest) * 100) / 100); } }); calc(); const nm = pg.querySelector('input'); if (!nm.value.trim()) nm.focus(); }
    });
    $('plCancel').addEventListener('click', () => { $('plSheet').hidden = true; });
    $('plX').addEventListener('click', () => { $('plSheet').hidden = true; });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('plSheet').hidden) $('plSheet').hidden = true; });
    // «⋯» فوق: فيها «تسوية رصيد».
    $('blDots').addEventListener('click', (e) => { e.stopPropagation(); $('blDotsMenu').hidden = !$('blDotsMenu').hidden; });
    document.addEventListener('click', (e) => { if (!e.target.closest('.bl-hmore')) $('blDotsMenu').hidden = true; });
    $('plSheet').addEventListener('click', (e) => { if (e.target.id === 'plSheet') $('plSheet').hidden = true; });
    $('plSheet').querySelector('form').addEventListener('submit', () => {
        grid.querySelectorAll('[data-cur]').forEach((el) => { el.value = String(num(el.value) || ''); });
    });
})();
</script>
@endsection
