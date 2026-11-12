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

    {{-- المجموع بسعر اليوم (12/11/2026، صورة م. محمد): مربع بعرض الصفحة، جواته بالعمود الأول العنوان والرقم وتحتهم
         الدولار والدينار بنفس شكل ومكان صفوف العملات بكروت الأدراج (للعرض بس)، وزر التعديل مربع لحاله على اليسار. --}}
    <section class="bl-panel bl-hero2">
        <div class="bl-pgrid">
            <div class="bl-hcell">
                <span class="bl-name">المجموع بسعر اليوم</span>
                <b class="bl-money bl-htotal {{ $total < 0 ? 'due' : '' }}" dir="ltr">{{ $total < 0 ? '−' : '' }}₪ {{ number_format(abs($total), 2) }}</b>
                <span class="bl-curs">
                    @foreach($currencies->where('code', '!=', 'ILS') as $c)
                        <span class="bl-cur {{ isset($fx[$c->code]) ? '' : 'zero' }}"><span>{{ \App\Support\DrawerPlaces::curName($c->code, $c->name) }} {{ $c->symbol }}</span><b dir="ltr">{{ isset($fx[$c->code]) ? '₪ '.rtrim(rtrim(number_format($fx[$c->code], 4), '0'), '.') : '—' }}</b></span>
                    @endforeach
                </span>
            </div>
        </div>
        <button type="button" class="bl-hedit" data-rates-open aria-label="تعديل سعر اليوم" title="تعديل سعر اليوم">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 18])</button>
    </section>

    {{-- الأدراج كلها جوا مربع واحد بنفس عرض مربع المجموع، لآخر الشاشة. --}}
    <section class="bl-panel bl-drawers">
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
    </section>
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

{{-- سعر اليوم: الأسعار المحفوظة، وجنب كل عملة سعر مقترح من مصدر رسمي بزر «استعمله». ما بينحفظ إشي إلا بـ«حفظ». --}}
<div class="cl-sheet tw-sheet" id="blRatesSheet" hidden>
    <form class="tw-dialog" method="post" action="{{ route('cp.balances.rates') }}" role="dialog" aria-modal="true" aria-labelledby="blRatesT">
        @csrf
        <div class="tw-head">
            <h2 id="blRatesT">سعر اليوم</h2>
            <button type="button" class="tw-x" data-close aria-label="إغلاق">@include('cp.partials.bs-icon', ['name' => 'close', 'size' => 18])</button>
        </div>
        <div class="tw-body">
            <p class="tw-hint" style="margin:0">كم شيكل بتسوى الوحدة. السعر للمجاميع بس، وكل حركة محفوظة بسعرها وقت التسجيل.</p>
            @foreach($currencies->where('code', '!=', 'ILS') as $c)
                <div class="bl-rrow">
                    <label class="tw-l" for="blRate{{ $c->code }}">{{ \App\Support\DrawerPlaces::curName($c->code, $c->name) }} {{ $c->symbol }} بكم شيكل</label>
                    <input id="blRate{{ $c->code }}" name="rates[{{ $c->code }}]" class="tw-mid bl-rin" inputmode="decimal" dir="ltr" value="{{ isset($rates[$c->code]) ? rtrim(rtrim(number_format($rates[$c->code], 4, '.', ''), '0'), '.') : '' }}" placeholder="مثلاً 3.70" autocomplete="off">
                    <div class="bl-sug" data-sug="{{ $c->code }}" hidden><span></span><button type="button" class="bl-use" data-use="{{ $c->code }}">استعمله</button></div>
                </div>
            @endforeach
            <small class="tw-hint" id="blSugSrc">بنجيب السعر الرسمي…</small>
        </div>
        <div class="tw-foot">
            <span class="tw-grow"></span>
            <button type="submit" class="tw-pri">حفظ</button>
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
        if (e.target.closest('[data-rates-open]')) { open($('blRatesSheet')); suggest(); return; }
        const x = e.target.closest('.tw-sheet [data-close]');
        if (x) { shut(x.closest('.tw-sheet')); return; }
        if (e.target.classList && e.target.classList.contains('tw-sheet') && e.target.id !== 'twSheet') { shut(e.target); return; }
        if (!e.target.closest('.cl-menu')) closeMenus();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') ['blSheet', 'blRatesSheet'].forEach((id) => { if (!$(id).hidden) shut($(id)); }); });

    // السعر المقترح: بينجاب لما تفتح النافذة بس، و«استعمله» بيعبّي الحقل (الحفظ بإيدك).
    let asked = false;
    function suggest() {
        if (asked) return; asked = true;
        fetch(@json(route('cp.balances.rates.suggest')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => r.json())
            .then((d) => {
                if (!d.ok) { $('blSugSrc').textContent = 'ما قدرنا نجيب السعر الرسمي هلأ. اكتب السعر بإيدك.'; return; }
                Object.entries(d.rates).forEach(([c, v]) => {
                    const box = document.querySelector('[data-sug="' + c + '"]');
                    if (!box) return;
                    box.querySelector('span').innerHTML = 'المقترح اليوم <b dir="ltr">₪ ' + v + '</b>' + ((d.derived || []).includes(c) ? ' <small>(محسوب من الدولار)</small>' : '');
                    box.querySelector('button').dataset.val = v;
                    box.hidden = false;
                });
                const day = d.as_of ? d.as_of.split('-').map(Number) : null;
                $('blSugSrc').textContent = 'المصدر: ' + d.source + (day ? '، ' + day[2] + '/' + day[1] + '/' + day[0] : '') + '. اقتراح بس، ما بينحفظ إلا بـ«حفظ».';
            })
            .catch(() => { $('blSugSrc').textContent = 'ما قدرنا نجيب السعر الرسمي هلأ. اكتب السعر بإيدك.'; asked = false; });
    }
    document.addEventListener('click', (e) => {
        const u = e.target.closest('[data-use]');
        if (!u || !u.dataset.val) return;
        const inp = $('blRate' + u.dataset.use); inp.value = u.dataset.val; inp.focus();
    });
})();
</script>
@endsection
