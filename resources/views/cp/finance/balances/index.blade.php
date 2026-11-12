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

    {{-- المجموع بسعر اليوم (12/11/2026، صورة م. محمد): بعرض كرت «نقدي» بدون إطار. سعر صرف الدولار والدينار نص للعرض بس.
         القلم بيحوّل الأرقام لحقول بنفس المكان (بدون نافذة)، مع السعر العالمي اليوم و«استعمله»، و«حفظ» بيشتغل لما يتغيّر رقم. --}}
    <div class="bl-grid bl-sumrow">
        <form class="bl-card bl-sum" id="blSum" method="post" action="{{ route('cp.balances.rates') }}">
            @csrf
            <div class="bl-main">
                <span class="bl-name">المجموع بسعر اليوم</span>
                <b class="bl-money {{ $total < 0 ? 'due' : '' }}" dir="ltr">{{ $total < 0 ? '−' : '' }}₪ {{ number_format(abs($total), 2) }}</b>
                <span class="bl-rates">
                    @foreach($currencies->where('code', '!=', 'ILS') as $c)
                        @php($v = isset($rates[$c->code]) ? rtrim(rtrim(number_format($rates[$c->code], 4, '.', ''), '0'), '.') : '')
                        @php($nm = 'سعر صرف '.\App\Support\DrawerPlaces::curDef($c->code, $c->name))
                        <span class="bl-rx" data-code="{{ $c->code }}">
                            <label class="bl-rl" for="blRate{{ $c->code }}">{{ $nm }}</label>
                            <span class="bl-rline bl-rshow"><b dir="ltr">{{ isset($fx[$c->code]) ? '₪ '.rtrim(rtrim(number_format($fx[$c->code], 4), '0'), '.') : '—' }}</b><button type="button" class="bl-pe" data-edit="{{ $c->code }}" aria-label="تعديل {{ $nm }}" title="تعديل {{ $nm }}">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 14])</button></span>
                            <span class="bl-rline bl-redit" hidden>
                                <input id="blRate{{ $c->code }}" name="rates[{{ $c->code }}]" class="bl-rin" inputmode="decimal" dir="ltr" value="{{ $v }}" data-orig="{{ $v }}" placeholder="3.70" autocomplete="off" disabled>
                                <button type="button" class="bl-bank" data-use="{{ $c->code }}" disabled><span>سعر البنك</span><b dir="ltr">…</b></button>
                                <button type="submit" class="bl-save" disabled>حفظ</button>
                            </span>
                        </span>
                    @endforeach
                </span>
            </div>
        </form>
    </div>

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
                </a>
                <button type="button" class="cl-more" aria-label="خيارات {{ $card['method']->name }}">⋯</button>
                <div class="cl-menu" hidden>
                    <button type="button" data-settle="{{ $card['method']->id }}" data-name="{{ $card['method']->name }}">تسوية رصيد</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- تسوية رصيد (12/11/2026): نفس شكل نافذة التحويل. العملة أزرار مش قائمة، وبيبيّن اللي بالنظام والفرق قبل الحفظ. --}}
<div class="cl-sheet tw-sheet" id="blSheet" hidden>
    <form class="tw-dialog" method="post" action="{{ route('cp.balances.settle') }}" role="dialog" aria-modal="true" aria-labelledby="blSheetT" id="blSettle">
        @csrf
        <input type="hidden" name="payment_method_id" id="blMethod">
        <input type="hidden" name="currency_id" id="blCur">
        <div class="tw-head">
            <h2 id="blSheetT">تسوية رصيد</h2>
            <button type="button" class="tw-x" data-close aria-label="إغلاق">@include('cp.partials.bs-icon', ['name' => 'close', 'size' => 18])</button>
        </div>
        <div class="tw-body">
            <span class="tw-l">العملة</span>
            <div class="tw-seg" id="blCurSeg">
                @foreach($currencies as $c)<button type="button" data-cur="{{ $c->id }}" data-code="{{ $c->code }}">{{ \App\Support\DrawerPlaces::curName($c->code, $c->name) }} {{ $c->symbol }}</button>@endforeach
            </div>
            <div class="tw-rv"><span>اللي بالنظام</span><span id="blHave" dir="ltr"></span></div>
            <label class="tw-l" for="blActual">الموجود فعلياً</label>
            <input id="blActual" name="actual" class="tw-big" inputmode="decimal" dir="ltr" placeholder="0.00" autocomplete="off" required>
            <div class="tw-rv bl-diff" id="blDiffRow" hidden><span>الفرق اللي رح ينسجل</span><span id="blDiff" dir="ltr"></span></div>
            <label class="tw-l" for="blNotes">ليش الفرق؟</label>
            <textarea id="blNotes" name="notes" class="tw-in tw-area" rows="2" required placeholder="ملاحظة إجبارية" maxlength="500"></textarea>
            <small class="tw-err" id="blErr">@error('actual'){{ $message }}@enderror @error('notes'){{ $message }}@enderror</small>
        </div>
        <div class="tw-foot">
            <span class="tw-grow"></span>
            <button type="submit" class="tw-pri" id="blSave" disabled>سجّل التسوية</button>
        </div>
    </form>
</div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const BAL = @json(collect($wizard['methods'])->mapWithKeys(fn ($m) => [$m['id'] => $m['bal']]));
    const SYM = @json($currencies->mapWithKeys(fn ($c) => [$c->code => $c->symbol]));
    const fmt = (v) => (v < 0 ? '−' : '') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (s) => { const t = String(s || '').replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/,/g, '').replace('٫', '.').trim(); const v = parseFloat(t); return t !== '' && isFinite(v) ? v : NaN; };
    const closeMenus = () => document.querySelectorAll('.bl-card .cl-menu').forEach((m) => { m.hidden = true; });
    const open = (el, focus) => { el.hidden = false; document.body.style.overflow = 'hidden'; if (focus) setTimeout(() => focus.focus(), 40); };
    const shut = (el) => { el.hidden = true; document.body.style.overflow = ''; };

    // تسوية رصيد
    let method = null, code = null;
    function paint() {
        document.querySelectorAll('#blCurSeg [data-cur]').forEach((b) => b.classList.toggle('on', b.dataset.code === code));
        const have = (BAL[method] || {})[code] || 0;
        $('blHave').textContent = (SYM[code] || code) + ' ' + fmt(have);
        const a = num($('blActual').value);
        const diff = isNaN(a) ? NaN : Math.round((a - have) * 100) / 100;
        $('blDiffRow').hidden = isNaN(diff);
        if (!isNaN(diff)) { $('blDiff').textContent = (diff > 0 ? '+' : '') + (SYM[code] || code) + ' ' + fmt(diff); $('blDiff').className = diff < 0 ? 'due' : ''; }
        $('blSave').disabled = isNaN(diff) || Math.abs(diff) < 0.005 || !$('blNotes').value.trim();
        $('blErr').textContent = !isNaN(diff) && Math.abs(diff) < 0.005 ? 'الموجود نفس اللي بالنظام، ما في فرق.' : '';
    }
    $('blCurSeg').addEventListener('click', (e) => { const b = e.target.closest('[data-cur]'); if (!b) return; code = b.dataset.code; $('blCur').value = b.dataset.cur; paint(); $('blActual').focus(); });
    $('blActual').addEventListener('input', paint);
    $('blNotes').addEventListener('input', paint);

    document.addEventListener('click', (e) => {
        const more = e.target.closest('.bl-card .cl-more');
        if (more) { e.stopPropagation(); const m = more.nextElementSibling; const was = m.hidden; closeMenus(); m.hidden = !was; return; }
        const s = e.target.closest('[data-settle]');
        if (s) {
            closeMenus(); method = +s.dataset.settle;
            $('blMethod').value = method; $('blSheetT').textContent = 'تسوية رصيد · ' + s.dataset.name;
            const first = document.querySelector('#blCurSeg [data-cur]');
            code = first.dataset.code; $('blCur').value = first.dataset.cur;
            $('blActual').value = ''; $('blNotes').value = ''; paint();
            open($('blSheet'), $('blActual')); return;
        }
        const x = e.target.closest('.tw-sheet [data-close]');
        if (x) { shut(x.closest('.tw-sheet')); return; }
        if (e.target.classList && e.target.classList.contains('tw-sheet') && e.target.id !== 'twSheet') { shut(e.target); return; }
        if (!e.target.closest('.cl-menu')) closeMenus();
    });
    document.addEventListener('keydown', (e) => { if (e.key !== 'Escape') return; if (!$('blSheet').hidden) shut($('blSheet')); else if (editing) editMode(null); });

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
        if (editing) { const r = rowOf(editing), i = r.querySelector('.bl-rin'); i.value = i.dataset.orig; i.disabled = true; r.querySelector('.bl-rshow').hidden = false; r.querySelector('.bl-redit').hidden = true; }
        editing = code || null;
        sum.classList.toggle('editing', !!editing);
        if (!editing) return;
        const r = rowOf(editing), i = r.querySelector('.bl-rin');
        r.querySelector('.bl-rshow').hidden = true; r.querySelector('.bl-redit').hidden = false; i.disabled = false;
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
