@extends('cp.layout')
@section('title', 'الأدراج والأرصدة')
@section('content')
@php
    $n = fn ($v) => ($v < 0 ? '−' : '').number_format(abs((float) $v), 2).' ₪';
    $tabs = $funds->mapWithKeys(fn ($f) => [$f->slug => $f->slug === 'family' ? 'شخصي' : ($f->slug === 'business' ? 'عمل' : $f->name)])->all() + ['all' => 'الكل'];
    $fx = collect($rates)->except('ILS');
@endphp
<div class="cl-page bl-page">
    <div class="cl-top">
        <h1 class="cl-h">الأدراج والأرصدة</h1>
        <div class="cl-sp"></div>
        @include('cp.partials.transfer-wizard', ['wizard' => $wizard])
    </div>

    {{-- شريط الملخص (12/11/2026، صورة summary-strip المعتمدة): بنفس شكل الكروت. يمين: المجموع كبير وتحته اللي معك فعلياً بكل عملة.
         يسار: سعر صرف الدولار والدينار جنب بعض، كل واحد بقلم بيعدّله بنفس السطر (الحقل، سعر البنك، حفظ). --}}
    @php
        $mix = $currencies->map(fn ($c) => ['name' => \App\Support\DrawerPlaces::curName($c->code, $c->name), 'sum' => collect($cards)->sum(fn ($k) => collect($k['currencies'])->firstWhere('code', $c->code)['amount'] ?? 0)]);
    @endphp
    <form class="bl-card bl-strip" id="blSum" method="post" action="{{ route('cp.balances.rates') }}">
        @csrf
        <div class="bl-stot">
            <span class="bl-name">المجموع بسعر اليوم</span>
            <b class="bl-money bl-big {{ $total < 0 ? 'due' : '' }}" dir="ltr">{{ $total < 0 ? '−' : '' }}₪ {{ number_format(abs($total), 2) }}</b>
            <span class="bl-mix">
                @foreach($mix as $m)
                    <span><i>{{ $m['name'] }}</i><b class="bl-money {{ $m['sum'] < 0 ? 'due' : '' }}" dir="ltr">{{ $m['sum'] < 0 ? '−' : '' }}{{ number_format(abs($m['sum']), 2) }}</b></span>
                @endforeach
            </span>
        </div>
        <div class="bl-rates">
            @foreach($currencies->where('code', '!=', 'ILS') as $c)
                @php
                    $v = isset($rates[$c->code]) ? rtrim(rtrim(number_format($rates[$c->code], 4, '.', ''), '0'), '.') : '';
                    $nm = 'سعر صرف '.\App\Support\DrawerPlaces::curDef($c->code, $c->name);
                @endphp
                <div class="bl-rx" data-code="{{ $c->code }}">
                    <label class="bl-rl" for="blRate{{ $c->code }}">{{ $nm }}</label>
                    <span class="bl-rline bl-rshow"><b dir="ltr">{{ isset($fx[$c->code]) ? '₪ '.number_format($fx[$c->code], 3) : '—' }}</b><button type="button" class="bl-pe" data-edit="{{ $c->code }}" aria-label="تعديل {{ $nm }}" title="تعديل {{ $nm }}">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 15])</button></span>
                    <span class="bl-rline bl-redit" hidden>
                        <input id="blRate{{ $c->code }}" name="rates[{{ $c->code }}]" class="bl-rin" inputmode="decimal" dir="ltr" value="{{ $v }}" data-orig="{{ $v }}" placeholder="3.70" autocomplete="off" disabled>
                        <button type="button" class="bl-bank" data-use="{{ $c->code }}" disabled><span>سعر البنك</span><b dir="ltr">…</b></button>
                        <button type="submit" class="bl-save" disabled>حفظ</button>
                    </span>
                </div>
            @endforeach
        </div>
    </form>

    <div class="bl-grid">
        @foreach($cards as $card)
            <div class="bl-card">
                <a class="bl-main" href="{{ route('cp.balances.show', ['method' => $card['method']->id]) }}">
                    <span class="bl-name">{{ $card['method']->name }}</span>
                    <b class="bl-money {{ $card['ils'] < 0 ? 'due' : '' }}">{{ $n($card['ils']) }}</b>
                    <span class="bl-curs">
                        @foreach($card['currencies'] as $c)
                            <span class="bl-cur {{ $c['zero'] ? 'zero' : '' }}"><span>{{ $c['name'] }} {{ $c['symbol'] }}</span><b class="bl-money {{ $c['negative'] ? 'due' : '' }}" dir="ltr">{{ $c['label'] }}</b></span>
                        @endforeach
                    </span>
                    @if($total > 0)
                        @php $pct = max(0, min(100, round($card['ils'] / $total * 100))); @endphp
                        <span class="bl-share" title="حصته من المجموع"><span class="bl-bar"><i style="width: {{ $pct }}%"></i></span><span dir="ltr">{{ $pct }}%</span></span>
                    @endif
                </a>
                <button type="button" class="cl-more" aria-label="خيارات {{ $card['method']->name }}">⋯</button>
                <div class="cl-menu" hidden>
                    <button type="button" data-settle="{{ $card['method']->id }}" data-name="{{ $card['method']->name }}">تسوية رصيد</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

@include('cp.partials.settle-sheet')

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const fmt = (v) => (v < 0 ? '−' : '') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (s) => { const t = String(s || '').replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/,/g, '').replace('٫', '.').trim(); const v = parseFloat(t); return t !== '' && isFinite(v) ? v : NaN; };
    const closeMenus = () => document.querySelectorAll('.bl-card .cl-menu').forEach((m) => { m.hidden = true; });

    document.addEventListener('click', (e) => {
        const more = e.target.closest('.bl-card .cl-more');
        if (more) { e.stopPropagation(); const m = more.nextElementSibling; const was = m.hidden; closeMenus(); m.hidden = !was; return; }
        if (!e.target.closest('.cl-menu')) closeMenus();
    });
    document.addEventListener('keydown', (e) => { if (e.key !== 'Escape') return; if ($('blSheet') && !$('blSheet').hidden) return; if (editing) editMode(null); });

    // سعر الصرف (12/11/2026): كل عملة إلها قلم، وبتتعدّل لحالها بمكانها. ✕ بيرجّع الرقم زي ما كان.
    const sum = $('blSum');
    let editing = null;
    function rowOf(code) { return sum.querySelector('.bl-rx[data-code="' + code + '"]'); }
    function dirty() {
        if (!editing) return;
        const r = rowOf(editing), i = r.querySelector('.bl-rin');
        r.querySelector('.bl-save').disabled = i.value.trim() === i.dataset.orig || !(num(i.value) > 0);
    }
    function editMode(code) {
        if (editing) { const r = rowOf(editing), i = r.querySelector('.bl-rin'); r.classList.remove('ed'); i.value = i.dataset.orig; i.disabled = true; r.querySelector('.bl-rshow').hidden = false; r.querySelector('.bl-redit').hidden = true; }
        editing = code || null;
        sum.classList.toggle('editing', !!editing);
        if (!editing) return;
        const r = rowOf(editing), i = r.querySelector('.bl-rin');
        r.classList.add('ed'); r.querySelector('.bl-rshow').hidden = true; r.querySelector('.bl-redit').hidden = false; i.disabled = false;
        suggest(); dirty(); setTimeout(() => { i.focus(); i.select(); }, 30);
    }
    sum.addEventListener('click', (e) => {
        const ed = e.target.closest('[data-edit]'); if (ed) { editMode(ed.dataset.edit); return; }
        const u = e.target.closest('[data-use]'); if (u && u.dataset.val) { $('blRate' + u.dataset.use).value = u.dataset.val; dirty(); }
    });
    // ضغطة برا سطر العملة اللي بتتعدّل بتلغي التعديل.
    document.addEventListener('click', (e) => { if (editing && !e.target.closest('.bl-rx[data-code="' + editing + '"]') && !e.target.closest('[data-edit]')) editMode(null); });
    sum.addEventListener('input', (e) => { if (e.target.classList.contains('bl-rin')) dirty(); });

    // السعر العالمي: بينجاب لما تفتح التعديل بس، و«استعمله» بيعبّي الحقل (الحفظ بإيدك).
    let asked = false;
    const noSug = () => { sum.querySelectorAll('.bl-bank').forEach((b) => { b.querySelector('b').textContent = '—'; b.disabled = true; b.title = 'ما قدرنا نجيب سعر البنك هلأ. اكتب السعر بإيدك.'; }); };
    function suggest() {
        if (asked) return; asked = true;
        fetch(@json(route('cp.balances.rates.suggest')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => r.json())
            .then((d) => {
                if (!d.ok) { noSug(); return; }
                const day = d.as_of ? d.as_of.split('-').map(Number) : null;
                const src = 'المصدر: ' + d.source + (day ? '، ' + day[2] + '/' + day[1] + '/' + day[0] : '');
                sum.querySelectorAll('.bl-bank').forEach((btn) => {
                    const c = btn.dataset.use, v = d.rates[c];
                    if (!v) { btn.querySelector('b').textContent = '—'; return; }
                    btn.querySelector('b').textContent = String(v); btn.dataset.val = v; btn.disabled = false;
                    btn.title = src + ((d.derived || []).includes(c) ? ' (محسوب من الدولار)' : '') + '. اضغط عشان تستعمله.';
                });
            })
            .catch(() => { noSug(); asked = false; });
    }
})();
</script>
@endsection
