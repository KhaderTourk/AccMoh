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
                <span class="bl-rview">
                    @foreach($currencies->where('code', '!=', 'ILS') as $c)
                        <span class="bl-rv"><span>سعر صرف {{ \App\Support\DrawerPlaces::curDef($c->code, $c->name) }}</span><b dir="ltr">{{ isset($fx[$c->code]) ? '₪ '.rtrim(rtrim(number_format($fx[$c->code], 4), '0'), '.') : '—' }}</b></span>
                    @endforeach
                </span>
                <span class="bl-redit" hidden>
                    @foreach($currencies->where('code', '!=', 'ILS') as $c)
                        @php($v = isset($rates[$c->code]) ? rtrim(rtrim(number_format($rates[$c->code], 4, '.', ''), '0'), '.') : '')
                        <span class="bl-re">
                            <label for="blRate{{ $c->code }}">سعر صرف {{ \App\Support\DrawerPlaces::curDef($c->code, $c->name) }}</label>
                            <input id="blRate{{ $c->code }}" name="rates[{{ $c->code }}]" class="bl-rin" inputmode="decimal" dir="ltr" value="{{ $v }}" data-orig="{{ $v }}" placeholder="مثلاً 3.70" autocomplete="off">
                            <span class="bl-sug" data-sug="{{ $c->code }}"><span>العالمي اليوم <b dir="ltr">…</b></span><button type="button" class="bl-use" data-use="{{ $c->code }}" hidden>استعمله</button></span>
                        </span>
                    @endforeach
                    <small class="bl-src" id="blSugSrc"></small>
                    <button type="submit" class="bl-save" id="blSave" disabled>حفظ</button>
                </span>
            </div>
            <button type="button" class="bl-penc" id="blEdit" aria-label="تعديل سعر الصرف" title="تعديل سعر الصرف">
                <span class="bl-pi">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 16])</span><span class="bl-xi">@include('cp.partials.bs-icon', ['name' => 'close', 'size' => 16])</span>
            </button>
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
    document.addEventListener('keydown', (e) => { if (e.key !== 'Escape') return; if (!$('blSheet').hidden) shut($('blSheet')); else if ($('blSum').classList.contains('editing')) editMode(false); });

    // سعر الصرف: القلم بيفتح التعديل بنفس المكان، ✕ بيرجّع الأرقام زي ما كانت.
    const sum = $('blSum'), ins = () => sum.querySelectorAll('.bl-rin');
    function dirty() { $('blSave').disabled = ![...ins()].some((i) => i.value.trim() !== i.dataset.orig && !(num(i.value) <= 0)); }
    function editMode(on) {
        sum.classList.toggle('editing', on);
        sum.querySelector('.bl-rview').hidden = on; sum.querySelector('.bl-redit').hidden = !on;
        $('blEdit').setAttribute('aria-label', on ? 'إلغاء التعديل' : 'تعديل سعر الصرف'); $('blEdit').title = $('blEdit').getAttribute('aria-label');
        if (on) { suggest(); setTimeout(() => ins()[0] && ins()[0].focus(), 30); } else { ins().forEach((i) => { i.value = i.dataset.orig; }); }
        dirty();
    }
    $('blEdit').addEventListener('click', () => editMode(!sum.classList.contains('editing')));
    sum.addEventListener('input', (e) => { if (e.target.classList.contains('bl-rin')) dirty(); });
    sum.addEventListener('click', (e) => { const u = e.target.closest('[data-use]'); if (!u || !u.dataset.val) return; const i = $('blRate' + u.dataset.use); i.value = u.dataset.val; dirty(); });

    // السعر العالمي: بينجاب لما تفتح التعديل بس، و«استعمله» بيعبّي الحقل (الحفظ بإيدك).
    let asked = false;
    const noSug = () => { $('blSugSrc').textContent = 'ما قدرنا نجيب السعر العالمي هلأ. اكتب السعر بإيدك.'; sum.querySelectorAll('.bl-sug').forEach((b) => { b.hidden = true; }); };
    function suggest() {
        if (asked) return; asked = true;
        fetch(@json(route('cp.balances.rates.suggest')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => r.json())
            .then((d) => {
                if (!d.ok) { noSug(); return; }
                sum.querySelectorAll('[data-sug]').forEach((box) => {
                    const c = box.dataset.sug, v = d.rates[c];
                    if (!v) { box.hidden = true; return; }
                    box.querySelector('b').textContent = String(v);
                    const btn = box.querySelector('button'); btn.dataset.val = v; btn.hidden = false;
                });
                const day = d.as_of ? d.as_of.split('-').map(Number) : null;
                $('blSugSrc').textContent = 'المصدر: ' + d.source + (day ? '، ' + day[2] + '/' + day[1] + '/' + day[0] : '') + ((d.derived || []).length ? '. الدينار محسوب من الدولار' : '');
            })
            .catch(() => { noSug(); asked = false; });
    }
})();
</script>
@endsection
