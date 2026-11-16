@extends('cp.layout')
@section('title', $title)
@section('content')
@php
    $kind = $party['kind'];
    $isPerson = $kind === 'person';
@endphp
<div class="cl-page cs-page" id="csPage">
    <div class="cs-head">
        <a href="{{ $urls['index'] }}" class="cl-ghost" title="{{ $party['plural'] }}"><span class="material-symbols-outlined">arrow_forward</span></a>
        <div class="cl-av big">{{ mb_substr(trim($title), 0, 1) ?: '؟' }}</div>
        <div class="cl-who">
            <h1 class="cl-h">{{ $title }}</h1>
            <span>{{ $subtitle }}</span>
            @unless($model->is_active)<em class="cs-arch">مؤرشف</em>@endunless
        </div>
        <div class="cs-adds">
            @if($isPerson)
                <div class="cs-add">
                    <button type="button" class="cs-btn svc" data-menu="mG1"><span class="material-symbols-outlined">north_east</span> مدين <small class="cs-dir">عليه</small></button>
                    <div class="cl-menu cs-menu" id="mG1" hidden></div>
                </div>
                <div class="cs-add">
                    <button type="button" class="cs-btn pay" data-menu="mG2"><span class="material-symbols-outlined">south_west</span> دائن <small class="cs-dir">إله</small></button>
                    <div class="cl-menu cs-menu" id="mG2" hidden></div>
                </div>
            @else
                <div class="cs-add">
                    <button type="button" class="cs-btn svc" id="bCharge" data-menu="mDue"><span class="material-symbols-outlined">add</span> مستحق</button>
                    <div class="cl-menu cs-menu" id="mDue" hidden></div>
                </div>
                <div class="cs-add">
                    <button type="button" class="cs-btn pay" data-menu="mG2"><span class="material-symbols-outlined">add</span> دفعة</button>
                    <div class="cl-menu cs-menu" id="mG2" hidden></div>
                </div>
            @endif
            <div class="cs-add">
                <button type="button" class="cs-btn other" data-menu="mOther"><span class="material-symbols-outlined">layers</span> أخرى</button>
                <div class="cl-menu cs-menu" id="mOther" hidden>
                    @if($isPerson)
                        <button type="button" data-o="transfer">تحويل رصيد</button>
                        <button type="button" data-o="memo">رصيد سابق</button>
                    @elseif($kind === 'worker')
                        <button type="button" data-o="discount">خصم</button>
                        <button type="button" data-o="memo">رصيد سابق</button>
                    @else
                        <button type="button" data-o="goods">بضاعة أخدها منا</button>
                        <button type="button" data-o="discount">خصم</button>
                        <button type="button" data-o="memo">رصيد سابق</button>
                    @endif
                </div>
            </div>
            <div class="cs-add">
                <button type="button" class="cl-more" data-menu="mParty" aria-label="خيارات">⋯</button>
                <div class="cl-menu cs-menu" id="mParty" hidden>
                    {{-- نفس خيارات ⋯ بالقائمة (15/11/2026) --}}
                    <a href="{{ $urls['pdf'] }}" target="_blank" rel="noopener">كشف حساب</a>
                    <a href="{{ $urls['edit'] }}">تعديل</a>
                    @if($kind === 'worker')
                        <a href="{{ route('cp.workers.index', ['edit' => $model->id, 'panel' => 'subs', 'back' => 'show']) }}">راتب شهري</a>
                    @endif
                    <hr>
                    @if($model->is_active)
                        <button type="button" class="danger" id="csArchive">أرشفة</button>
                    @else
                        <button type="button" id="csRestore">استرجاع من الأرشيف</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('cp.partials.note-card', ['notes' => $model->notes])

    <div class="cs-balance" id="csBalance"></div>

    @if($kind === 'supplier')
        <section class="st-card cs-subs" id="csSubs" hidden>
            <header class="st-card-h"><h2>{{ $kind === 'worker' ? 'الرواتب الشهرية' : 'الاشتراكات الشهرية' }}</h2><span class="st-count" id="csSubsN"></span>
                <a href="{{ $urls['index'] }}" class="cs-subs-link">{{ $kind === 'worker' ? 'الرواتب الشهرية' : 'الخدمات الشهرية' }}</a></header>
            <div id="csSubsList"></div>
        </section>
    @endif

    <div class="cs-tabs" role="tablist">
        <button type="button" class="on" data-view="list" role="tab">الحركات</button>
        <button type="button" data-view="stmt" role="tab">كشف حساب</button>
    </div>

    <div class="cs-filters">
        <div class="cl-seg" id="csType">
            <button type="button" class="on" data-t="all">الكل</button>
            <button type="button" data-t="g1">{{ $party['g1'] }}</button>
            <button type="button" data-t="g2">{{ $party['g2'] }}</button>
            <button type="button" data-t="other">أخرى</button>
        </div>
        @include('cp.partials.period-filter')
        <a class="cp-btn cs-pdf" id="csPdf" href="#" target="_blank" rel="noopener" hidden><span class="material-symbols-outlined">picture_as_pdf</span> تصدير الكشف PDF</a>
    </div>
    <div class="cs-chips" id="csChips"></div>

    <div class="cs-sum" id="csSum"></div>
    <div class="cl-list cs-table" id="csTable"></div>
</div>

<div class="cl-sheet" id="txSheet" hidden>
    <form class="cl-dialog" id="txForm" method="post" novalidate>
        @csrf
        <h2 id="txTitle"></h2>
        <div id="txHidden"></div>
        <div id="txFields"></div>
        <p class="cs-after" id="txAfter"></p>
        <p class="cl-dup" id="txDup" hidden><span class="material-symbols-outlined">warning</span> <span id="txDupTxt"></span></p>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary" id="txSave" disabled>حفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" id="txCancel">إلغاء</button>
        </div>
    </form>
</div>

<form method="post" id="csDelForm" hidden>@csrf<input type="hidden" name="_method" value="DELETE"></form>
<form method="post" id="csArchForm" action="{{ $urls['archive'] }}" hidden>@csrf @if($model->is_active)<input type="hidden" name="_method" value="DELETE">@endif</form>

@unless($isPerson)@include('cp.partials.monthly-stop')@endunless
<script type="application/json" id="csData">{!! json_encode([
    'party' => $party,
    'subscriptions' => $subscriptions ?? [],
    'id' => $model->id,
    'title' => $title,
    'movements' => $movements,
    'lookups' => $lookups,
    'urls' => $urls,
    'today' => now()->toDateString(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@if($kind === 'worker')<script src="{{ asset('assets/js/cp-date.js') }}?v={{ @filemtime(public_path('assets/js/cp-date.js')) }}"></script>@endif
<script>
(function () {
    const D = JSON.parse(document.getElementById('csData').textContent);
    const P = D.party, L = D.lookups, M = D.movements;
    const PERSON = P.kind === 'person', WORKER = P.kind === 'worker';
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const num = (n) => Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const sym = (code) => (L.currencies.find((c) => c.code === code) || {}).symbol || code;
    const dayLabel = (d) => { if (!d) return ''; const [y, m, dd] = d.split('-').map(Number); return dd + '/' + m + '/' + y; };
    // عنوان اليوم بالحركات (12/11/2026): «اليوم · 12/11/2026»، «مبارح · …».
    const dayHead = (d) => { const t = new Date(D.today + 'T00:00:00'), y = new Date(t); y.setDate(t.getDate() - 1); const k = (x) => x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0'); return (d === D.today ? 'اليوم · ' : d === k(y) ? 'مبارح · ' : '') + dayLabel(d); };
    const iso = (dt) => dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
    const MOTION = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    /* اللون من جهة الطرف: «عليه» وردي و«إله» أخضر. عند الموظف والمورد الموجب = إله */
    const tone = (v) => Math.abs(v) < 0.005 ? 'zero' : ((PERSON ? v : -v) > 0 ? 'due' : 'credit');
    const word = (v) => Math.abs(v) < 0.005 ? 'مسدّد' : (v > 0 ? P.pos : P.neg);

    let view = 'list', cType = 'all', sub = new Set(), range = null, preset = 'all';

    function balanceBy(code) { return M.filter((m) => m.currency === code).reduce((a, m) => a + m.delta, 0); }
    function renderBalance() {
        const codes = [...new Set(M.map((m) => m.currency))];
        if (!codes.length) codes.push('ILS');
        // شريط الملخص (12/11/2026، أسلوب الأدراج): الرصيد كبير، وعلى اليسار عدد الحركات وآخر حركة.
        const big = codes.map((code) => {
            const v = balanceBy(code);
            const cls = tone(v);
            return `<b class="bl-big cs-v ${cls}" dir="ltr">${cls === 'zero' ? '0.00' : num(v)} ${esc(sym(code))}</b>`;
        }).join('');
        const settled = codes.every((code) => Math.abs(balanceBy(code)) < 0.005);
        const last = M.length ? M.reduce((a, m) => (m.date > a ? m.date : a), M[0].date) : null;
        $('csBalance').innerHTML = `<div class="bl-card bl-strip cs-strip"><div class="bl-stot"><span class="bl-name">${settled ? 'مسدّد' : 'الرصيد'}</span>${big}</div>`
            + `<div class="bl-rates"><div class="bl-rx bl-cb"><span class="bl-rl">عدد الحركات</span><b dir="ltr">${M.length}</b></div>`
            + `<div class="bl-rx bl-cb ${last ? '' : 'zero'}"><span class="bl-rl">آخر حركة</span><b dir="ltr">${last ? dayLabel(last) : '—'}</b></div></div></div>`;
    }

    /* ——— الفترة ——— */
    function presets() {
        const now = new Date(D.today + 'T00:00:00');
        const y = now.getFullYear(), m = now.getMonth();
        const d = (yy, mm, dd) => iso(new Date(yy, mm, dd));
        return [
            ['all', 'كل الوقت', null, null],
            ['month', 'هالشهر', d(y, m, 1), d(y, m + 1, 0)],
            ['last', 'الشهر الماضي', d(y, m - 1, 1), d(y, m, 0)],
            ['m3', 'آخر 3 شهور', d(y, m - 2, 1), d(y, m + 1, 0)],
            ['year', 'هالسنة', d(y, 0, 1), d(y, 11, 31)],
            ['lyear', 'السنة الماضية', d(y - 1, 0, 1), d(y - 1, 11, 31)],
        ];
    }
    function renderPresets() { $('csPresets').innerHTML = presets().map((p) => `<button type="button" data-p="${p[0]}" class="${preset === p[0] ? 'on' : ''}">${p[1]}</button>`).join(''); }
    function rangeText() { return range ? (preset === 'custom' ? `${dayLabel(range.from)} – ${dayLabel(range.to)}` : range.label) : 'كل الوقت'; }
    $('csRangeBtn').addEventListener('click', (e) => { e.stopPropagation(); const menu = $('csRangeMenu'); const open = menu.hidden; closeMenus(); menu.hidden = !open; renderPresets(); });
    $('csPresets').addEventListener('click', (e) => {
        const b = e.target.closest('[data-p]'); if (!b) return;
        const p = presets().find((x) => x[0] === b.dataset.p);
        preset = p[0]; range = p[2] ? { from: p[2], to: p[3], label: p[1] } : null;
        closeMenus(); render();
    });
    $('csApply').addEventListener('click', () => {
        let f = cpPeriod.iso($('csFrom')), t = cpPeriod.iso($('csTo'));
        if (!f && !t) return;
        if (!f) f = '1900-01-01';
        if (!t) t = D.today;
        if (f > t) [f, t] = [t, f];
        preset = 'custom'; range = { from: f, to: t, label: 'مخصص' };
        closeMenus(); render();
    });

    /* ——— الفلاتر ——— */
    $('csType').addEventListener('click', (e) => {
        const b = e.target.closest('[data-t]'); if (!b) return;
        cType = b.dataset.t; sub = new Set();
        $('csType').querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b));
        render();
    });
    $('csChips').addEventListener('click', (e) => { const b = e.target.closest('[data-k]'); if (!b) return; const k = b.dataset.k; sub.has(k) ? sub.delete(k) : sub.add(k); render(); });
    document.querySelectorAll('.cs-tabs button').forEach((b) => b.addEventListener('click', () => {
        view = b.dataset.view;
        document.querySelectorAll('.cs-tabs button').forEach((x) => x.classList.toggle('on', x === b));
        render();
    }));
    function passesType(m) { return (cType === 'all' || m.group === cType) && (!sub.size || sub.has(m.kind)); }
    function renderChips() {
        if (cType === 'all') { $('csChips').innerHTML = ''; return; }
        const kinds = [...new Set(M.filter((m) => m.group === cType).map((m) => m.kind))];
        $('csChips').innerHTML = kinds.length > 1 ? kinds.map((k) => `<button type="button" data-k="${esc(k)}" class="${sub.has(k) ? 'on' : ''}">${esc(k)}</button>`).join('') : '';
    }

    /* ——— الجدول والكشف ——— */
    function rows() {
        const run = {};
        const all = M.map((m, i) => ({ ...m, i }));
        all.forEach((m) => { run[m.currency] = (run[m.currency] || 0) + m.delta; m.after = run[m.currency]; });
        const before = {};
        const list = all.filter((m) => {
            if (range && m.date < range.from) { if (passesType(m)) before[m.currency] = (before[m.currency] || 0) + m.delta; return false; }
            if (range && m.date > range.to) return false;
            return passesType(m);
        });
        return { list, before };
    }
    // سطر العملة الأصلية (16/11/2026): «5 دولار بسعر 3.7 · طلعت من درج الدولار» قبل الملاحظة.
    function subLine(m) { const t = [m.fx, m.notes].filter(Boolean).join(' · '); return t ? `<small>${esc(t)}</small>` : ''; }
    function amountCell(m) {
        const cls = tone(m.delta);
        return `<span class="cl-amt ${cls}">${m.delta < 0 ? '−' : ''}${num(m.amount)} ${esc(m.symbol)}</span>`;
    }
    function balCell(v, code) {
        const cls = tone(v);
        return `<span class="cs-run ${cls}">${cls === 'zero' ? 'مسدّد' : num(v) + ' ' + esc(sym(code))}</span>`;
    }
    function render() {
        renderChips();
        $('csRangeTxt').textContent = rangeText();
        $('csRangeBtn').classList.toggle('on', !!range);
        const { list, before } = rows();
        const filtered = cType !== 'all' || sub.size;
        const pdf = $('csPdf');
        pdf.hidden = view !== 'stmt';
        const qs = new URLSearchParams();
        if (range) { qs.set('from', dayLabel(range.from)); qs.set('to', dayLabel(range.to)); }
        if (cType !== 'all') { qs.set('type', cType); sub.forEach((k) => qs.append('kinds[]', k)); }
        pdf.href = D.urls.pdf + (qs.toString() ? '?' + qs.toString() : '');

        const tIn = list.filter((m) => m.delta > 0).reduce((a, m) => a + m.delta, 0);
        const tOut = list.filter((m) => m.delta < 0).reduce((a, m) => a - m.delta, 0);
        const beforeCodes = Object.keys(before).filter((c) => Math.abs(before[c]) >= 0.005);
        $('csSum').innerHTML = `<span>${list.length} حركة</span>`
            + (tIn ? ` · <span>${esc(P.pos)} <b class="${tone(1)}">${num(tIn)}</b></span>` : '')
            + (tOut ? ` · <span>نزل <b class="${tone(-1)}">${num(tOut)}</b></span>` : '')
            + (beforeCodes.length ? ` · <span>رصيد سابق ${beforeCodes.map((c) => num(before[c]) + ' ' + esc(sym(c))).join('، ')}</span>` : '');

        if (view === 'list') {
            const ordered = list.slice().reverse();
            $('csTable').innerHTML = ordered.length ? `<div class="cs-row cs-hrow">${cType === 'all' ? '<span></span>' : ''}<span>الحركة</span><span>المبلغ</span><span>${filtered ? '' : 'الرصيد بعدها'}</span><span></span></div>` + ordered.map((m, i) => `${i === 0 || ordered[i - 1].date !== m.date ? `<div class="cs-day">${dayHead(m.date)}</div>` : ''}
                <div class="cs-row${cType === 'all' ? '' : ' no-ic'}" data-id="${esc(m.id)}">
                    ${cType === 'all' ? `<span class="cs-ic ${m.group === 'g1' ? 'svc' : (m.group === 'g2' ? 'pay' : 'other')}"><span class="material-symbols-outlined">${esc(m.icon)}</span></span>` : ''}
                    <span class="cs-t"><b>${esc(m.title)}</b>${subLine(m)}</span>
                    ${amountCell(m)}
                    ${filtered ? '<span></span>' : balCell(m.after, m.currency)}
                    <span class="cs-act"><button type="button" class="cl-more" aria-label="خيارات">⋯</button>
                        <div class="cl-menu" hidden><a href="${esc(m.edit)}">تعديل</a><hr><button type="button" class="danger" data-del="${esc(m.id)}">حذف</button></div></span>
                </div>`).join('')
                : `<p class="cl-empty">ما في حركات${range || filtered ? ' بهالفلتر' : ' لسا'}.</p>`;
        } else {
            const run = {};
            beforeCodes.forEach((c) => { run[c] = before[c]; });
            const head = `<div class="cs-st cs-hrow"><span>التاريخ</span><span>البيان</span>${WORKER ? '<span>مدين<small>دفعات</small></span><span>دائن<small>مستحقات</small></span>' : `<span>مدين<small>${esc(P.pos)}</small></span><span>دائن<small>${esc(P.neg)}</small></span>`}<span>${filtered ? 'المجموع' : 'الرصيد'}</span></div>`;
            const opening = range ? `<div class="cs-st cs-open"><span>${dayLabel(range.from)}</span><span>رصيد سابق</span><span></span><span></span><span>${beforeCodes.length ? beforeCodes.map((c) => num(before[c]) + ' ' + esc(sym(c))).join('<br>') : '0.00'}</span></div>` : '';
            let dr = 0, cr = 0;
            const body = list.map((m) => {
                run[m.currency] = (run[m.currency] || 0) + m.delta;
                if (m.delta > 0) dr += m.delta; else cr -= m.delta;
                return `<div class="cs-st"><span>${dayLabel(m.date)}</span><span>${esc(m.title)}${subLine(m)}</span>${WORKER ? `<span>${m.delta < 0 ? num(-m.delta) : ''}</span><span>${m.delta > 0 ? num(m.delta) : ''}</span>` : `<span>${m.delta > 0 ? num(m.delta) : ''}</span><span>${m.delta < 0 ? num(-m.delta) : ''}</span>`}<span>${num(run[m.currency])} ${esc(sym(m.currency))}</span></div>`;
            }).join('');
            const foot = `<div class="cs-st cs-foot"><span></span><span>المجموع</span><span>${num(WORKER ? cr : dr)}</span><span>${num(WORKER ? dr : cr)}</span><span>${Object.keys(run).map((c) => num(run[c]) + ' ' + esc(sym(c))).join('<br>') || '0.00'}</span></div>`;
            $('csTable').innerHTML = list.length || range ? head + opening + body + foot : '<p class="cl-empty">ما في حركات لسا.</p>';
        }
        if (MOTION) { $('csTable').classList.remove('cs-anim'); void $('csTable').offsetWidth; $('csTable').classList.add('cs-anim'); }
    }

    /* ——— القوائم ——— */
    function closeMenus() { document.querySelectorAll('.cl-menu').forEach((m) => { m.hidden = true; }); }
    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-menu]');
        if (t) { e.stopPropagation(); const menu = $(t.dataset.menu); const open = menu.hidden; closeMenus(); menu.hidden = !open; return; }
        const more = e.target.closest('.cs-act .cl-more');
        if (more) { e.stopPropagation(); const menu = more.nextElementSibling; const open = menu.hidden; closeMenus(); menu.hidden = !open; return; }
        if (!e.target.closest('.cl-menu')) closeMenus();
    });
    const methodButtons = L.methods.map((p) => `<button type="button" data-m="${p.id}">${esc(p.name)}</button>`).join('') || '<p class="cs-mnote">ما في طرق دفع مفعّلة.</p>';
    if ($('mG1')) {
        $('mG1').innerHTML = methodButtons;
        $('mG1').addEventListener('click', (e) => { const b = e.target.closest('[data-m]'); if (b) { closeMenus(); openTx('give', { methodId: +b.dataset.m }); } });
    }
    $('mG2').innerHTML = methodButtons;
    $('mG2').addEventListener('click', (e) => { const b = e.target.closest('[data-m]'); if (b) { closeMenus(); openTx(PERSON ? 'take' : 'pay', { methodId: +b.dataset.m }); } });
    if ($('mDue')) {
        const types = L.dueTypes || [];
        $('mDue').innerHTML = (types.length ? `<b class="cs-mh">${WORKER ? 'مستحقات الموظفين' : 'خدمات الموردين'}</b>` : '')
            + types.map((t) => `<button type="button" data-due="${t.id}">${esc(t.name)}</button>`).join('')
            + '<button type="button" data-due="free">أخرى</button><hr>'
            + `<a href="${esc(L.manageTypes)}" class="cs-manage">إدارة الأنواع ←</a>`;
        $('mDue').addEventListener('click', (e) => {
            const b = e.target.closest('[data-due]'); if (!b) return;
            closeMenus();
            const typeId = b.dataset.due === 'free' ? null : +b.dataset.due;
            const t = types.find((x) => x.id === typeId);
            // الموظف (المرحلة 3، 15/11/2026): النوع اللي هو راتب شهري عنده بيفتح نافذة الشهر، والباقي النافذة العادية.
            if (WORKER) { const s = t ? (D.subscriptions || []).find((x) => +x.type === t.id && !x.to) : null; openW(s ? 'wsub' : 'wcharge', { type: t, sub: s }); return; }
            openTx(WORKER && t && t.name === 'راتب' ? 'sub' : 'charge', { typeId });
        });
    }
    $('mOther').addEventListener('click', (e) => { const b = e.target.closest('[data-o]'); if (b) { closeMenus(); openTx(b.dataset.o, {}); } });

    $('csTable').addEventListener('click', (e) => {
        const b = e.target.closest('[data-del]'); if (!b) return;
        closeMenus();
        const m = M.find((x) => x.id === b.dataset.del);
        const after = balanceBy(m.currency) - m.delta;
        const msg = `حذف «${m.title}» بتاريخ ${dayLabel(m.date)}؟ الرصيد بعد الحذف: ${Math.abs(after) < 0.005 ? 'مسدّد' : word(after) + ' ' + num(after) + ' ' + sym(m.currency)}.` + (m.kind === 'تحويل رصيد' ? ' وبينحذف كمان من الحساب التاني.' : '');
        const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
        ask.then((ok) => { if (!ok) return; const f = $('csDelForm'); f.action = m.delete; f.submit(); });
    });
    const arch = $('csArchive') || $('csRestore');
    if (arch) arch.addEventListener('click', () => {
        closeMenus();
        if (arch.id === 'csRestore') { $('csArchForm').submit(); return; }
        const v = balanceBy('ILS');
        const msg = `أرشفة «${D.title}»؟` + (Math.abs(v) >= 0.005 ? ` رصيده ${word(v)} ${num(v)} ₪ وبيضل محفوظ مع حركاته.` : '') + ' بيختفي من القوائم، وبتقدر تشوفه من الإعدادات ← العرض ← «إظهار المؤرشف».';
        const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
        ask.then((ok) => { if (ok) $('csArchForm').submit(); });
    });

    /* ——— نافذة الحركة ——— */
    // sign = what the movement does to this page's balance (positive = «إله» / «بدي منه»).
    const KIND = {
        charge: { title: 'مستحق', url: D.urls.charge, sign: 1, date: 'charge_date', amtLabel: 'المبلغ', group: 'g1' },
        sub: { title: 'مستحق', url: D.urls.sub, sign: 0, date: 'starts_on', amtLabel: WORKER ? 'الراتب الشهري' : 'الاتفاق الشهري', group: 'g1' },
        pay: { title: 'دفعة', url: D.urls.payment, sign: -1, date: 'occurred_on', amtLabel: 'المبلغ', group: 'g2' },
        give: { title: 'مدين · عليه', url: D.urls.give, sign: 1, date: 'occurred_on', amtLabel: 'المبلغ', group: 'g1' },
        take: { title: 'دائن · إله', url: D.urls.take, sign: -1, date: 'occurred_on', amtLabel: 'المبلغ', group: 'g2' },
        advance: { title: 'سلفة', url: D.urls.payment, sign: -1, date: 'occurred_on', amtLabel: 'المبلغ', group: 'other' },
        discount: { title: 'خصم', url: D.urls.goods, sign: -1, date: 'taken_on', amtLabel: 'قيمة الخصم', group: 'other' },
        goods: { title: 'بضاعة أخدها منا', url: D.urls.goods, sign: -1, date: 'taken_on', amtLabel: 'قيمة البضاعة', group: 'other' },
        memo: { title: 'رصيد سابق', url: D.urls.memo, sign: 0, date: 'memo_date', amtLabel: 'المبلغ', group: 'other' },
        transfer: { title: 'تحويل رصيد', url: D.urls.transfer, sign: 0, date: 'memo_date', amtLabel: 'المبلغ', group: 'other' },
    };
    let tx = null;
    const hidden = (n, v) => `<input type="hidden" name="${n}" value="${esc(v)}">`;
    const curOptions = () => L.currencies.map((c) => `<option value="${c.id}" data-code="${esc(c.code)}">${esc(c.code)} ${esc(c.symbol)}</option>`).join('');
    function dateField(name) {
        return `<div class="cl-field"><label for="xDate">التاريخ</label><div class="cs-date-in">
            <input id="xDate" name="${name}" inputmode="numeric" dir="ltr" placeholder="يوم/شهر/سنة" value="${dayLabel(D.today)}">
            <button type="button" class="cs-today" id="xToday">اليوم</button>
            <label class="cs-cal" title="اختار من التقويم"><span class="material-symbols-outlined">calendar_month</span><input type="date" id="xPick" tabindex="-1"></label>
        </div><small class="cl-err" id="xDateErr" hidden>التاريخ لازم يكون يوم/شهر/سنة</small></div>`;
    }
    function moneyFields(label) {
        return `<div class="cs-two"><div class="cl-field"><label for="xAmt">${label}</label><input id="xAmt" inputmode="decimal" dir="ltr" placeholder="0.00"><small class="cs-hint" id="xHint"></small></div>
            <div class="cl-field"><label for="xCur">العملة</label><select id="xCur" name="currency_id">${curOptions()}</select></div></div>
            <div class="cl-field" id="xRateWrap" hidden><label for="xRate" id="xRateL">سعر الصرف</label><input id="xRate" name="exchange_rate" inputmode="decimal" dir="ltr"><small class="cs-hint" id="xIls"></small></div>
            <input type="hidden" name="amount" id="xAmtIls"><input type="hidden" name="source_amount" id="xAmtSrc">`;
    }
    function plainMoney() {
        return `<div class="cs-two"><div class="cl-field"><label for="xAmt">المبلغ</label><input id="xAmt" name="amount" inputmode="decimal" dir="ltr" placeholder="0.00"></div>
            <div class="cl-field"><label for="xCur">العملة</label><select id="xCur" name="currency_id">${curOptions()}</select></div></div>`;
    }
    const seg = (a, b) => `<div class="cl-seg cs-dir" id="xDir"><button type="button" class="on" data-d="${a[0]}">${a[1]}</button><button type="button" data-d="${b[0]}">${b[1]}</button></div>`;
    function monthLabel() { const d = new Date(D.today + 'T00:00:00'); return (d.getMonth() + 1) + '/' + d.getFullYear(); }

    function openTx(kind, opt) {
        const K = KIND[kind];
        tx = { kind, opt, plain: kind === 'memo' || kind === 'transfer' };
        let title = K.title, fields = '', hid = '';
        const pk = L.partyKey;
        if (kind === 'charge' || kind === 'sub') {
            // «مستحق»: النوع من الإعدادات، وبعدها «بالقطعة/لمرة وحدة» أو «شهري» (قرار 11/11/2026)
            const t = opt.typeId ? (L.dueTypes || []).find((x) => x.id === opt.typeId) : null;
            title = 'مستحق · ' + (t ? t.name : 'أخرى');
            hid = hidden('vendor_id', D.id) + (t ? hidden('charge_type_id', t.id) : '');
            const what = t ? '' : `<div class="cl-field"><label for="xWhat">البيان</label><input id="xWhat" name="title" maxlength="255" required placeholder="${WORKER ? 'مثلاً: بدل مواصلات' : 'شو استلمنا منه'}"></div>`;
            fields = billSeg(kind) + what + (kind === 'sub'
                ? cpMonthlySheet.fields({ amtLabel: K.amtLabel })
                : moneyFields(K.amtLabel) + dateField(K.date));
        } else if (kind === 'pay' || kind === 'give' || kind === 'take') {
            const p = L.methods.find((x) => x.id === opt.methodId);
            title = K.title + ' · ' + p.name;
            hid = hidden('party_key', pk) + hidden('fund_id', L.fundId) + hidden('payment_method_id', p.id) + hidden('name', D.title);
            fields = moneyFields(K.amtLabel) + dateField(K.date);
            // الموظف: كل اللي بيطلعله «دفعة»، والسلفة صارت وصف (تسليم 1/11 §12)
            if (WORKER && kind === 'pay') fields += `<div class="cl-field"><label for="xNotes">الوصف</label><input id="xNotes" name="notes" maxlength="255" placeholder="اكتب أو اختار">
                <div class="cs-chips cs-desc" id="xDesc">${['راتب', 'سلفة', 'مكافأة', 'عمل إضافي'].map((w) => `<button type="button" data-w="${w}">${w}</button>`).join('')}</div></div>`;
        } else if (kind === 'advance') {
            const opts = L.methods.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
            hid = hidden('party_key', pk) + hidden('fund_id', L.fundId) + hidden('name', D.title) + hidden('kind', 'advance');
            fields = `<div class="cl-field"><label for="xMethod">طلعت من</label><select id="xMethod" name="payment_method_id" class="cs-sel">${opts}</select></div>`
                + moneyFields(K.amtLabel) + dateField(K.date);
        } else if (kind === 'discount' || kind === 'goods') {
            const [t, id] = pk.split(':');
            hid = hidden('party_key', pk) + hidden('party_type', t) + hidden('party_id', id) + hidden('category', kind === 'goods' ? 'other' : 'discount');
            fields = (kind === 'goods' ? '<div class="cl-field"><label for="xWhat">شو أخد</label><input id="xWhat" name="title" maxlength="255" required></div>' : '')
                + moneyFields(K.amtLabel) + dateField(K.date);
        } else if (kind === 'memo') {
            const [t, id] = pk.split(':');
            hid = hidden('party_key', pk) + hidden('party_type', t) + hidden('party_id', id) + hidden('reason', 'opening');
            // owed_to_us: they owe us. For a worker or supplier «إله» is the opposite.
            const a = PERSON ? ['owed_to_us', P.pos] : ['owed_by_us', P.pos];
            const b = PERSON ? ['owed_by_us', P.neg] : ['owed_to_us', 'عليه'];
            fields = `<div class="cl-field"><label>الرصيد</label>${seg(a, b)}<input type="hidden" name="direction" id="xDirIn" value="${a[0]}"></div>`
                + plainMoney() + dateField(K.date);
        } else if (kind === 'transfer') {
            const opts = L.targets.map((t) => `<option value="${esc(t.key)}">${esc(t.name)} · ${esc(t.type)}</option>`).join('');
            hid = hidden('direction', 'owed_to_us') + '<input type="hidden" name="source_party_key" id="xSrc"><input type="hidden" name="target_party_key" id="xDst">';
            fields = `<div class="cl-field"><label>الاتجاه</label>${seg(['out', 'من حسابه لحساب تاني'], ['in', 'من حساب تاني لحسابه'])}<input type="hidden" id="xDirIn" value="out"></div>
                <div class="cl-field"><label for="xOther">الحساب التاني</label><select id="xOther" class="cs-sel">${opts}</select><small class="cs-hint" id="xOtherAfter"></small></div>`
                + plainMoney() + dateField(K.date);
        }
        if (!fields.includes('id="xNotes"')) fields += '<div class="cl-field"><label for="xNotes">ملاحظات</label><textarea id="xNotes" name="notes" rows="2" placeholder="اختياري"></textarea></div>';
        $('txTitle').textContent = title;
        $('txHidden').innerHTML = hid;
        $('txFields').innerHTML = fields;
        $('txForm').action = K.url;
        $('txSave').disabled = true;
        $('txSheet').hidden = false;
        if (kind === 'sub') {
            const okWhat = () => !$('xWhat') || $('xWhat').value.trim().length > 0;
            let m = null;
            m = cpMonthlySheet.wire({ today: D.today, amtLabel: K.amtLabel, fromLabel: WORKER ? 'بلّش شغل يوم' : 'بلّشنا يوم', listName: WORKER ? 'الرواتب الشهرية' : 'الخدمات الشهرية',
                onChange: () => { $('txSave').disabled = !(m && m.valid() && okWhat()); } });
            $('txFields').addEventListener('input', () => { $('txSave').disabled = !(m.valid() && okWhat()); });
            $('txSave').disabled = !(m.valid() && okWhat());
            $('txAfter').innerHTML = '';
            $('txDup').hidden = true;
            wireBill();
            setTimeout(() => ($('xWhat') || $('mAmt')).focus(), 30);
            return;
        }
        wireTx();
        wireBill();
        setTimeout(() => ($('xWhat') || $('xAmt')).focus(), 30);
    }
    // طريقة الحساب أول اختيار: الموظف «بالقطعة / راتب شهري»، والمورد «لمرة وحدة / اشتراك شهري»
    function billSeg(on) {
        const a = WORKER ? 'بالقطعة' : 'لمرة وحدة', b = WORKER ? 'راتب شهري' : 'اشتراك شهري';
        return `<div class="cl-field"><label>طريقة الحساب</label><div class="cl-seg" id="xBill">
            <button type="button" data-b="charge"${on === 'charge' ? ' class="on"' : ''}>${a}</button>
            <button type="button" data-b="sub"${on === 'sub' ? ' class="on"' : ''}>${b}</button></div></div>`;
    }
    function wireBill() {
        const bill = $('xBill');
        if (bill) bill.addEventListener('click', (e) => { const b = e.target.closest('[data-b]'); if (b && !b.classList.contains('on')) openTx(b.dataset.b, { typeId: tx.opt.typeId }); });
    }
    function wireTx() {
        const desc = $('xDesc');
        if (desc) desc.addEventListener('click', (e) => { const b = e.target.closest('[data-w]'); if (!b) return; $('xNotes').value = b.dataset.w; desc.querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b)); });
        const cur = $('xCur');
        const curCode = () => cur.selectedOptions[0].dataset.code;
        if ((tx.kind === 'pay' || tx.kind === 'take') && $('xHint')) {
            const v = balanceBy('ILS');
            const owe = tx.kind === 'pay' ? v : v;
            if (owe > 0.005) $('xHint').innerHTML = `${esc(P.pos)} ${num(owe)} ₪ · <button type="button" class="cs-fill" id="xFill">عبّي الكل</button>`;
        }
        const dir = $('xDir');
        if (dir) dir.addEventListener('click', (e) => { const b = e.target.closest('[data-d]'); if (!b) return; dir.querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b)); $('xDirIn').value = b.dataset.d; check(); });
        if ($('xOther')) $('xOther').addEventListener('change', check);
        cur.addEventListener('change', () => {
            const fx = curCode() !== 'ILS';
            if ($('xRateWrap')) { $('xRateL').textContent = 'سعر صرف ' + ({ USD: 'الدولار', JOD: 'الدينار', ILS: 'الشيكل' }[(cur.selectedOptions[0] && cur.selectedOptions[0].dataset.code) || ''] || 'العملة'); $('xRateWrap').hidden = !fx; }
            check();
        });
        $('xToday').addEventListener('click', () => { $('xDate').value = dayLabel(D.today); check(); });
        $('xPick').addEventListener('change', () => { if ($('xPick').value) $('xDate').value = dayLabel($('xPick').value); check(); });
        $('txFields').addEventListener('input', check);
        const fill = $('xFill'); if (fill) fill.addEventListener('click', () => { $('xAmt').value = balanceBy('ILS').toFixed(2); check(); });
        check();
    }
    function parseDay(v) {
        const m = String(v).trim().replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
        if (!m) return null;
        const dt = new Date(+m[3], +m[2] - 1, +m[1]);
        return dt.getMonth() === +m[2] - 1 ? iso(dt) : null;
    }
    function ilsAmount() {
        const a = parseFloat($('xAmt').value);
        if (!(a > 0)) return 0;
        const code = $('xCur').selectedOptions[0].dataset.code;
        if (code === 'ILS' || tx.plain) return a;
        return a * (parseFloat(($('xRate') || {}).value) || 0);
    }
    function check() {
        if (!tx || !$('xCur') || tx.w) return;
        const code = $('xCur').selectedOptions[0].dataset.code;
        const fx = code !== 'ILS' && !tx.plain;
        const a = parseFloat($('xAmt').value);
        const ils = ilsAmount();
        const day = parseDay($('xDate').value);
        $('xDateErr').hidden = !!day || $('xDate').value.length < 6;
        if (!tx.plain) {
            $('xAmtIls').value = fx ? '' : ($('xAmt').value || '');
            $('xAmtSrc').value = fx ? ($('xAmt').value || '') : '';
            if (fx && ils) $('xIls').textContent = '= ' + num(ils) + ' ₪';
        }
        let sign = KIND[tx.kind].sign;
        if (tx.kind === 'memo') {
            const v = $('xDirIn').value;
            sign = PERSON ? (v === 'owed_to_us' ? 1 : -1) : (v === 'owed_by_us' ? 1 : -1);
        }
        if (tx.kind === 'transfer') {
            const out = $('xDirIn').value === 'out';
            sign = out ? -1 : 1;
            const key = $('xOther').value;
            $('xSrc').value = out ? L.partyKey : key;
            $('xDst').value = out ? key : L.partyKey;
            const t = L.targets.find((x) => x.key === key);
            if (t && ils) {
                const after = t.owed + (out ? ils : -ils);
                $('xOtherAfter').textContent = `${t.name} بعدها: ${Math.abs(after) < 0.005 ? 'مسدّد' : (after > 0 ? 'عليه ' : 'إله ') + num(after) + ' ' + sym(code)}`;
            } else if ($('xOtherAfter')) { $('xOtherAfter').textContent = ''; }
        }
        const curKey = tx.plain ? code : 'ILS';
        const after = balanceBy(curKey) + sign * ils;
        $('txAfter').innerHTML = ils ? 'الرصيد بعدها: ' + balCell(after, curKey) + (Math.abs(after) >= 0.005 ? ` <small>(${word(after)})</small>` : '') : '';
        let dup = null;
        if (ils && day && tx.kind !== 'transfer') {
            const group = KIND[tx.kind].group;
            const dd = new Date(day + 'T00:00:00');
            dup = M.find((m) => m.group === group && Math.abs(Math.abs(m.delta) - ils) < 0.005 && Math.abs((new Date(m.date + 'T00:00:00') - dd) / 864e5) <= 3);
        }
        $('txDup').hidden = !dup;
        if (dup) $('txDupTxt').textContent = `في حركة بنفس المبلغ بتاريخ ${dayLabel(dup.date)}: ${dup.title}. تأكد إنها مش مكررة.`;
        const okWhat = !$('xWhat') || $('xWhat').value.trim().length > 0;
        const okRate = !fx || parseFloat(($('xRate') || {}).value) > 0;
        const okOther = tx.kind !== 'transfer' || !!$('xOther').value;
        $('txSave').disabled = !(a > 0 && day && okWhat && okRate && okOther);
    }
    function closeTx() { $('txSheet').hidden = true; tx = null; }
    $('txCancel').addEventListener('click', closeTx);
    $('txSheet').addEventListener('click', (e) => { if (e.target.id === 'txSheet') closeTx(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('txSheet').hidden) closeTx(); });
    $('txForm').addEventListener('submit', (e) => {
        if ($('txSave').disabled) { e.preventDefault(); return; }
        if ($('xDate')) $('xDate').value = dayLabel(parseDay($('xDate').value));
    });

    /* ——— «مستحق» للموظف (المرحلة 3، 15/11/2026): نفس نوافذ خدمة الزبون واشتراكه بالملّي (قرار 17 و18) ———
       المبلغ وجنبه زر عملة صغير، سعر الصرف بس مع عملة ثانية و«السعر العالمي»، التاريخ بالتقويم و«اليوم» لما يتغيّر،
       «رصيده هلأ» و«بعد المستحق» بالأخضر لأنه بيزيد اللي إله، و«احفظ» باهت بدون مبلغ. الراتب: «عن شهر» وبآخر يوم بالشهر. */
    const NB = (t) => `<bdi class="num" dir="ltr">${t}</bdi>`;
    const curOf = (id) => L.currencies.find((c) => +c.id === +id) || L.currencies.find((c) => c.code === 'ILS');
    const sysRate = (id) => +(L.rates || {})[id] || 0;
    function wMoney() {
        const items = L.currencies.map((c) => `<button type="button" role="option" data-cur="${c.id}">${esc(c.name || c.code)} ${esc(c.symbol)}</button>`).join('');
        return `<div class="cl-field"><label for="xAmt">المبلغ</label>
            <div class="cp-amt"><input id="xAmt" inputmode="decimal" dir="ltr" placeholder="0.00" autocomplete="off">
                <div class="cp-cur"><button type="button" class="cp-cur-b" id="xCurB" aria-haspopup="listbox" aria-label="العملة"><span id="xCurS"></span><span class="cp-cur-ar">▾</span></button>
                <div class="cp-cur-m" id="xCurM" role="listbox" hidden>${items}</div></div></div>
            <input type="hidden" name="currency_id" id="xCur" value="${L.ilsId}">
            <div class="cp-rate" id="xRateWrap" hidden><span>سعر الصرف</span><input id="xRate" name="exchange_rate" inputmode="decimal" dir="ltr" autocomplete="off"><span id="xIls"></span><small class="cp-rate-sys" id="xRateSys"></small></div>
            <small class="cp-hint" id="xHint"></small></div>
            <input type="hidden" name="amount" id="xAmtIls"><input type="hidden" name="source_amount" id="xAmtSrc">`;
    }
    const wNotes = () => '<div class="cl-field"><label for="xNotes">ملاحظة</label><input id="xNotes" name="notes" placeholder="اختياري" autocomplete="off"></div>';
    const pkey = (p) => p[0] + '-' + String(p[1] + 1).padStart(2, '0');
    const plabel = (p) => (p[1] + 1) + '/' + p[0];
    const padd = (p, k) => { const d = new Date(p[0], p[1] + k, 1); return [d.getFullYear(), d.getMonth()]; };
    const pcmp = (a, b) => a[0] * 12 + a[1] - (b[0] * 12 + b[1]);
    const pdim = (p) => new Date(p[0], p[1] + 1, 0).getDate();
    function nowP() { const t = new Date(D.today + 'T00:00:00'); return [t.getFullYear(), t.getMonth()]; }
    function startP(s) { const [y, m] = s.from.split('-').map(Number); return [y, m - 1]; }
    function firstMissing(s) { let p = startP(s); const n = nowP(); while (pcmp(p, n) <= 0) { if (!s.dates[pkey(p)]) return p; p = padd(p, 1); } return n; }
    function missedOf(s, cur) { const out = []; let p = startP(s); const n = nowP(); while (pcmp(p, n) < 0) { if (!s.dates[pkey(p)] && pcmp(p, cur) !== 0) out.push(p); p = padd(p, 1); } return out; }
    const isFirst = () => !Object.keys(tx.sub.dates).length && pcmp(tx.p, startP(tx.sub)) <= 0;
    function subDays() { const n = pdim(tx.p); return (tx.stop ? tx.d2 : n) - (isFirst() ? tx.d1 : 1) + 1; }
    function subSugg() { const n = pdim(tx.p), d = subDays(); return d >= n ? tx.sub.amount : Math.round(tx.sub.amount / n * d * 100) / 100; }
    function subNote() {
        const p = tx.p, f = isFirst(), st = tx.stop;
        const parts = [(st ? (f ? 'أول وآخر شهر · ' : 'آخر شهر · ') : '') + tx.sub.title + ' ' + plabel(p)];
        if (f && tx.d1 > 1) parts.push('من ' + tx.d1 + '/' + plabel(p));
        if (st) parts.push('لغاية ' + tx.d2 + '/' + plabel(p));
        if (subDays() < pdim(p)) parts.push(subDays() + ' يوم من ' + pdim(p));
        if (st) parts.push('وقف ' + tx.sub.title);
        return parts.join(' · ');
    }
    function subBar() {
        const s = tx.sub, d = s.dates[pkey(tx.p)], ms = missedOf(s, tx.p);
        return `<div class="cs-sbrow"><b>${esc(s.title)} شهري · ${NB(num(s.amount) + ' ₪')}</b>${ms.length ? `<span class="cs-miss">فاتك ${ms.map((p) => `<button type="button" data-gm="${pkey(p)}">${NB(plabel(p))}</button>`).join('')}</span>` : ''}</div>
            <div class="cs-sbrow"><span class="cs-mpick"><span>عن شهر</span><button type="button" class="cs-mar" data-mv="-1" aria-label="الشهر اللي قبل">›</button><b>${NB(plabel(tx.p))}</b><button type="button" class="cs-mar" data-mv="1" aria-label="الشهر اللي بعد">‹</button></span>
            <span class="cs-pill${d ? ' ok' : ''}">${d ? 'انضاف ✓ بتاريخ ' + NB(dayLabel(d)) : (isFirst() ? 'أول شهر' : 'لسا ما انضاف')}</span></div>`;
    }
    function subDayFields() {
        const p = tx.p, n = pdim(p), lock = pkey(p);
        if (!isFirst() && !tx.stop) return `<p class="cs-autod">بينسجل بتاريخ ${NB(dayLabel(lock + '-' + String(n).padStart(2, '0')))} (آخر يوم بالشهر)</p>`;
        const f = (id, lab, v, data) => `<div class="cl-field"><label for="${id}">${lab}</label>${cpDate.html({ id, value: v + '/' + plabel(p), today: dayLabel(D.today), data: { lock, ...data }, label: lab })}</div>`;
        let h = '';
        if (isFirst()) h += f('xD1', 'بلّش شغل يوم', tx.d1, { 'min-day': 1, 'max-day': tx.stop ? tx.d2 : n, 'range-to': tx.stop ? tx.d2 : n });
        if (tx.stop) h += f('xD2', 'آخر يوم شغل', tx.d2, { 'min-day': isFirst() ? tx.d1 : 1, 'max-day': n, 'range-from': isFirst() ? tx.d1 : 1 });
        const at = tx.stop ? tx.d2 + '/' + plabel(p) : n + '/' + plabel(p);
        return (isFirst() && tx.stop ? `<div class="cs-two">${h}</div>` : h) + `<p class="cs-autod">بينسجل بتاريخ ${NB(at)} ${tx.stop ? '(آخر يوم شغل)' : '(آخر يوم بالشهر)'} · ${subDays()} يوم من ${n}</p>`;
    }
    function subReset() {
        const n = pdim(tx.p);
        tx.d1 = 1; tx.d2 = pcmp(tx.p, nowP()) === 0 ? new Date(D.today + 'T00:00:00').getDate() : n;
        if (tx.d2 < tx.d1) tx.d2 = tx.d1;
        tx.manual = false;
    }
    function subPaint() {
        $('xSubBar').innerHTML = subBar();
        $('xDays').innerHTML = subDayFields();
        const d = tx.sub.dates[pkey(tx.p)];
        $('txDup').hidden = !d;
        if (d) $('txDupTxt').textContent = `${tx.sub.title} شهر ${plabel(tx.p)} انضاف قبل لهالموظف. متأكد بدك تضيفه مرة ثانية؟`;
        if (!tx.manual && curOf($('xCur').value).code === 'ILS') $('xAmt').value = String(subSugg());
        if (tx.noteAuto) $('xNotes').value = subNote();
        $('xPeriod').value = pkey(tx.p);
        $('xStartDay').value = isFirst() ? tx.d1 : '';
        $('xEndDay').value = tx.stop ? tx.d2 : '';
        $('xStopIn').value = tx.stop ? 1 : 0;
        $('xAgain').value = d ? 1 : 0;
        $('xStopNote').hidden = !tx.stop;
        wCheck();
    }
    function openW(kind, opt) {
        const t = opt.type;
        tx = { kind, w: true, type: t, sub: opt.sub || null };
        let hid, fields, action;
        if (tx.sub) {
            action = tx.sub.url;
            tx.p = firstMissing(tx.sub); tx.stop = false; tx.noteAuto = true; subReset();
            hid = '<input type="hidden" name="period" id="xPeriod"><input type="hidden" name="start_day" id="xStartDay"><input type="hidden" name="end_day" id="xEndDay"><input type="hidden" name="stop" id="xStopIn" value="0"><input type="hidden" name="again" id="xAgain" value="0">';
            fields = '<div class="cs-subbar" id="xSubBar"></div>' + wMoney() + '<div id="xDays"></div>' + wNotes()
                + '<label class="cs-stopt"><input type="checkbox" id="xStop"> هادا آخر شهر، الموظف وقّف شغل</label>'
                + `<p class="cs-autod" id="xStopNote" hidden>بعد الحفظ بينشال «${esc(tx.sub.title)}» من رواتبه الشهرية، والحركات القديمة بتضل.</p>`;
        } else {
            action = D.urls.charge;
            hid = hidden('vendor_id', D.id) + (t ? hidden('charge_type_id', t.id) : '');
            fields = (t ? '' : '<div class="cl-field"><label for="xWhat">البيان</label><input id="xWhat" name="title" maxlength="255" placeholder="مثلاً: بدل مواصلات"></div>')
                + wMoney()
                + `<div class="cl-field"><label for="xDate">التاريخ</label>${cpDate.html({ id: 'xDate', name: 'charge_date', today: dayLabel(D.today), label: 'التاريخ' })}<small class="cl-err" id="xDateErr" hidden>التاريخ لازم يكون يوم/شهر/سنة</small></div>`
                + wNotes();
        }
        $('txTitle').textContent = 'مستحق · ' + (t ? t.name : 'أخرى');
        $('txHidden').innerHTML = hid;
        $('txFields').innerHTML = fields;
        $('txForm').action = action;
        $('txSave').disabled = true;
        $('txSave').textContent = 'احفظ';
        $('txSheet').hidden = false;
        wSetCur(L.ilsId);
        if (tx.sub) subPaint(); else wCheck();
        setTimeout(() => { const f = $('xWhat') || $('xAmt'); f.focus(); if (tx && tx.sub) f.select(); }, 30);
    }
    function wSetCur(id) {
        const c = curOf(id);
        $('xCur').value = c.id;
        $('xCurS').textContent = c.symbol;
        $('xCurM').querySelectorAll('[data-cur]').forEach((b) => b.classList.toggle('on', +b.dataset.cur === +c.id));
        const fx = c.code !== 'ILS';
        $('xRateWrap').hidden = !fx;
        if (fx) { $('xRate').value = sysRate(c.id) || ''; $('xRate').previousElementSibling.textContent = 'سعر صرف ' + ({ USD: 'الدولار', JOD: 'الدينار' }[c.code] || c.code); }
    }
    function wIls() {
        const a = parseFloat($('xAmt').value);
        if (!(a > 0)) return 0;
        return curOf($('xCur').value).code === 'ILS' ? a : a * (parseFloat($('xRate').value) || 0);
    }
    function wHint() {
        const ils = curOf($('xCur').value).code === 'ILS', a = parseFloat($('xAmt').value) || 0;
        let html = '';
        if (tx.sub) {
            const sg = subSugg(), n = pdim(tx.p), d = subDays(), part = d < n;
            const same = ils && Math.abs(a - sg) < 0.005;
            const calc = part ? `${NB(num(tx.sub.amount))} ÷ ${n} يوم × ${d} يوم = ${NB(num(sg) + ' ₪')}` : `${esc(tx.sub.title)} الشهري: ${NB(num(tx.sub.amount) + ' ₪')}`;
            html = same ? (part ? 'الشهر مش كامل: ' + calc : '') : `${part ? 'المقترح: ' : ''}${calc} · <button type="button" class="cp-use" data-use="${sg}">استخدمه</button>`;
        } else if (tx.type && tx.type.last != null && !(ils && Math.abs(a - tx.type.last) < 0.005)) {
            html = `آخر مرة لهالموظف: ${NB(num(tx.type.last) + ' ₪')} · <button type="button" class="cp-use" data-use="${tx.type.last}">استخدمه</button>`;
        }
        $('xHint').innerHTML = html;
    }
    function wCheck() {
        if (!tx || !tx.w) return;
        const c = curOf($('xCur').value), fx = c.code !== 'ILS';
        const a = parseFloat($('xAmt').value), ils = wIls();
        const day = tx.sub ? true : parseDay($('xDate').value);
        if ($('xDateErr')) $('xDateErr').hidden = !!day || $('xDate').value.length < 6;
        $('xAmtIls').value = fx ? '' : ($('xAmt').value || '');
        $('xAmtSrc').value = fx ? ($('xAmt').value || '') : '';
        $('xIls').innerHTML = fx && ils ? '= <b>' + NB(num(ils) + ' ₪') + '</b>' : '';
        const sr = sysRate(c.id), cur = parseFloat($('xRate').value);
        $('xRateSys').innerHTML = !fx ? '' : (!sr || Math.abs(cur - sr) < 1e-9 ? 'السعر العالمي' : '<button type="button" class="cp-use" data-rate-sys="1" title="رجّع السعر العالمي">↺ السعر العالمي</button>');
        const nowV = balanceBy('ILS'), after = nowV + ils;
        const box = (lab, v) => { const cls = tone(v); return `<span class="cs-bl"><span>${lab}</span><b class="${cls}">${cls === 'zero' ? 'مسدّد' : NB(num(v) + ' ₪')}</b></span>`; };
        $('txAfter').innerHTML = box('رصيده هلأ', nowV) + (ils ? box('بعد المستحق', after) : '');
        wHint();
        if (tx.sub) {
            const d = tx.sub.dates[pkey(tx.p)];
            $('txSave').textContent = tx.stop ? 'أضف آخر شهر ووقّف' : (d ? 'أضيفه مرة ثانية' : 'أضف شهر ' + plabel(tx.p));
        } else {
            let dup = null;
            if (ils && day) {
                const dd = new Date(day + 'T00:00:00');
                dup = M.find((m) => m.group === 'g1' && Math.abs(Math.abs(m.delta) - ils) < 0.005 && Math.abs((new Date(m.date + 'T00:00:00') - dd) / 864e5) <= 3);
            }
            $('txDup').hidden = !dup;
            if (dup) $('txDupTxt').textContent = `في حركة بنفس المبلغ بتاريخ ${dayLabel(dup.date)}: ${dup.title}. تأكد إنها مش مكررة.`;
        }
        const okWhat = !$('xWhat') || $('xWhat').value.trim().length > 0;
        const okRate = !fx || parseFloat($('xRate').value) > 0;
        $('txSave').disabled = !(a > 0 && day && okWhat && okRate);
    }
    if (WORKER) {
        const F = $('txFields');
        F.addEventListener('click', (e) => {
            if (!tx || !tx.w) return;
            if (e.target.closest('#xCurB')) { e.stopPropagation(); $('xCurM').hidden = !$('xCurM').hidden; return; }
            const c = e.target.closest('[data-cur]');
            if (c) { $('xCurM').hidden = true; wSetCur(c.dataset.cur); if (tx.sub) tx.manual = true; wCheck(); $('xAmt').focus(); return; }
            const u = e.target.closest('[data-use]');
            if (u) { wSetCur(L.ilsId); $('xAmt').value = u.dataset.use; if (tx.sub) tx.manual = false; wCheck(); $('xAmt').focus(); return; }
            if (e.target.closest('[data-rate-sys]')) { $('xRate').value = sysRate($('xCur').value); wCheck(); return; }
            if (!tx.sub) return;
            const mv = e.target.closest('[data-mv]');
            if (mv) { tx.p = padd(tx.p, +mv.dataset.mv); subReset(); subPaint(); return; }
            const gm = e.target.closest('[data-gm]');
            if (gm) { const [y, m] = gm.dataset.gm.split('-').map(Number); tx.p = [y, m - 1]; subReset(); subPaint(); }
        });
        F.addEventListener('change', (e) => { if (tx && tx.w && tx.sub && e.target.id === 'xStop') { tx.stop = e.target.checked; const d1 = tx.d1; subReset(); tx.d1 = d1; if (tx.d2 < tx.d1) tx.d2 = tx.d1; subPaint(); } });
        F.addEventListener('input', (e) => {
            if (!tx || !tx.w) return;
            const id = e.target.id;
            if (tx.sub && (id === 'xD1' || id === 'xD2')) {
                const pp = cpDate.parse(e.target.value);
                if (pp && pp[0] === tx.p[0] && pp[1] === tx.p[1]) {
                    if (id === 'xD1') tx.d1 = Math.min(tx.stop ? tx.d2 : pdim(tx.p), pp[2]); else tx.d2 = Math.max(isFirst() ? tx.d1 : 1, pp[2]);
                    const keep = document.activeElement && document.activeElement.id;
                    const sel = keep === id ? [e.target.selectionStart, e.target.selectionEnd] : null;
                    const man = tx.manual; subPaint(); tx.manual = man;
                    if (sel) { const el = $(id); el.focus(); el.setSelectionRange(sel[0], sel[1]); }
                }
                return;
            }
            if (tx.sub && id === 'xAmt') tx.manual = true;
            if (tx.sub && id === 'xNotes') tx.noteAuto = false;
            wCheck();
        });
        F.addEventListener('keydown', (e) => {
            if (tx && tx.w && e.key === 'Enter' && ['xAmt', 'xRate', 'xDate', 'xD1', 'xD2'].includes(e.target.id)) { e.preventDefault(); if (!$('txSave').disabled) $('txForm').requestSubmit(); }
        });
        document.addEventListener('click', (e) => { const m = $('xCurM'); if (m && !m.hidden && !e.target.closest('.cp-cur')) m.hidden = true; });
    }

    /* ——— الرواتب والاشتراكات الشهرية ——— */
    function renderSubs() {
        const S = D.subscriptions || [];
        if (!$('csSubs')) return;
        $('csSubs').hidden = !S.length;
        if (!S.length) return;
        const active = S.filter((x) => !x.to).length;
        $('csSubsN').textContent = active ? active + ' فعّال' : 'ما في فعّال';
        $('csSubsList').innerHTML = S.map((x) => {
            const acts = (x.to ? '' : `<button type="button" data-sub-stop="${x.id}">وقّف</button>`)
                + (x.months ? '' : `<button type="button" class="danger" data-sub-del="${x.id}">حذف</button>`);
            return `<div class="st-row${x.to ? ' is-off' : ''}">
            <div class="st-name"><b>${esc(x.title)} · ${num(x.amount)} ₪ بالشهر</b>
            <span>من ${dayLabel(x.from)}${x.to ? ' · انوقف بآخر يوم شغل ' + dayLabel(x.to) : ''} · ${x.months} شهر منضاف</span>
            ${x.due ? `<a class="cs-sub-due" href="${esc(D.urls.index)}">${x.due === 1 ? 'في شهر' : 'في ' + x.due + ' شهور'} لسا ما انضاف ←</a>` : ''}</div>
            ${acts ? `<div class="st-act"><button type="button" class="cl-more" aria-label="خيارات">⋯</button>
            <div class="cl-menu" hidden>${acts}</div></div>` : ''}</div>`;
        }).join('');
    }
    if ($('csSubsList')) $('csSubsList').addEventListener('click', (e) => {
        const more = e.target.closest('.cl-more');
        if (more) { e.stopPropagation(); const m = more.nextElementSibling; const open = m.hidden; closeMenus(); m.hidden = !open; return; }
        const st = e.target.closest('[data-sub-stop]');
        if (st) { closeMenus(); cpMonthlySheet.openStop(D.subscriptions.find((y) => y.id === +st.dataset.subStop), { today: D.today, noun: WORKER ? 'الراتب' : 'الاشتراك' }); return; }
        const dl = e.target.closest('[data-sub-del]');
        if (dl) {
            closeMenus();
            const x = D.subscriptions.find((y) => y.id === +dl.dataset.subDel);
            const ask = window.cpConfirm ? window.cpConfirm(`حذف «${x.title}»؟`) : Promise.resolve(window.confirm('حذف؟'));
            ask.then((ok) => { if (ok) { const f = $('csDelForm'); f.action = x.delete; f.submit(); } });
        }
    });


    /* ——— ضل مكانك بعد الحفظ أو الحذف ——— */
    const kept = window.cpKeep && cpKeep.take();
    if (kept) {
        view = kept.view || view; cType = kept.cType || cType; sub = new Set(kept.sub || []);
        preset = kept.preset || preset; range = kept.range || null;
        document.querySelectorAll('.cs-tabs button').forEach((x) => x.classList.toggle('on', x.dataset.view === view));
        $('csType').querySelectorAll('button').forEach((x) => x.classList.toggle('on', x.dataset.t === cType));
        if (range && preset === 'custom') { $('csFrom').value = dayLabel(range.from); $('csTo').value = dayLabel(range.to); }
    }
    if (window.cpKeep) cpKeep.track(() => ({ view, cType, sub: [...sub], preset, range }));
    renderBalance();
    renderSubs();
    render();
    /* ——— التسجيل السريع: فتح نفس نافذة الحركة (§11) ——— */
    {
        const qk = new URLSearchParams(location.search).get('quick');
        const click = (sel) => setTimeout(() => { const el = document.querySelector(sel); if (el) el.click(); }, 60);
        if (PERSON) {
            if (qk === 'in') click('[data-menu=mG2]');
            else if (qk === 'out') click('[data-menu=mG1]');
            else if (qk === 'transfer') openTx('transfer', {});
        } else {
            if (qk === 'out') click('[data-menu=mG2]');
            else if (qk === 'take') click('[data-menu=mDue]');
            else if (qk === 'give' && KIND.goods && !WORKER) openTx('goods', {});
        }
        if (qk) history.replaceState(null, '', location.pathname);
    }
})();
</script>
@endsection
