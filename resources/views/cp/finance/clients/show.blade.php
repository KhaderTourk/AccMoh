@extends('cp.layout')
@section('title', $client->organization() ?: $client->personName())
@section('content')
@php
    $title = $client->organization() ?: $client->personName();
    $sub = trim(implode(' · ', array_filter([$client->organization() ? $client->personName() : null, $client->phone])));
@endphp
<div class="cl-page cs-page" id="csPage">
    <div class="cs-head">
        <a href="{{ route('cp.clients.index') }}" class="cl-ghost" title="الزبائن"><span class="material-symbols-outlined">arrow_forward</span></a>
        <div class="cl-av big">{{ mb_substr(trim($title), 0, 1) ?: '؟' }}</div>
        <div class="cl-who">
            <h1 class="cl-h">{{ $title }}</h1>
            <span>{{ $sub }}</span>
            @unless($client->is_active)<em class="cs-arch">مؤرشف</em>@endunless
        </div>
        <div class="cs-adds">
            <div class="cs-add">
                <button type="button" class="cs-btn svc" data-menu="mSvc"><span class="material-symbols-outlined">add</span> خدمة</button>
                <div class="cl-menu cs-menu" id="mSvc" hidden></div>
            </div>
            <div class="cs-add">
                <button type="button" class="cs-btn pay" data-menu="mPay"><span class="material-symbols-outlined">add</span> دفعة</button>
                <div class="cl-menu cs-menu" id="mPay" hidden></div>
            </div>
            <div class="cs-add">
                <button type="button" class="cs-btn other" data-menu="mOther"><span class="material-symbols-outlined">layers</span> أخرى</button>
                <div class="cl-menu cs-menu" id="mOther" hidden>
                    <button type="button" data-o="memo">رصيد سابق</button>
                    <button type="button" data-o="discount">خصم</button>
                    <button type="button" data-o="goods">دفعة بضاعة</button>
                    <button type="button" data-o="refund">استرداد</button>
                </div>
            </div>
            <div class="cs-add">
                <button type="button" class="cl-more" data-menu="mClient" aria-label="خيارات الزبون">⋯</button>
                <div class="cl-menu cs-menu" id="mClient" hidden>
                    {{-- نفس خيارات ⋯ بالقائمة (15/11/2026) --}}
                    <a href="{{ route('cp.clients.export-pdf', $client) }}" target="_blank" rel="noopener">كشف حساب</a>
                    <a href="{{ route('cp.clients.index', ['edit' => $client->id, 'back' => 'show']) }}">تعديل</a>
                    <a href="{{ route('cp.clients.index', ['edit' => $client->id, 'panel' => 'subs', 'back' => 'show']) }}">اشتراك شهري</a>
                    <hr>
                    @if($client->is_active)
                        <button type="button" class="danger" id="csArchive">أرشفة</button>
                    @else
                        <button type="button" id="csRestore">استرجاع من الأرشيف</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('cp.partials.note-card', ['notes' => $client->notes])

    <div class="cs-balance" id="csBalance"></div>


    <div class="cs-tabs" role="tablist">
        <button type="button" class="on" data-view="list" role="tab">الحركات</button>
        <button type="button" data-view="stmt" role="tab">كشف حساب</button>
    </div>

    <div class="cs-filters">
        <div class="cl-seg" id="csType">
            <button type="button" class="on" data-t="all">الكل</button>
            <button type="button" data-t="svc">خدمات</button>
            <button type="button" data-t="pay">دفعات</button>
            <button type="button" data-t="other">أخرى</button>
        </div>
        @include('cp.partials.period-filter')
        <a class="cp-btn cs-pdf" id="csPdf" href="#" target="_blank" rel="noopener" hidden><span class="material-symbols-outlined">picture_as_pdf</span> تصدير الكشف PDF</a>
    </div>
    <div class="cs-chips" id="csChips"></div>

    <div class="cs-sum" id="csSum"></div>
    <div class="cl-list cs-table" id="csTable"></div>
</div>

{{-- نافذة الحركة --}}
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
<form method="post" id="csArchForm" action="{{ $client->is_active ? route('cp.clients.destroy', $client) : route('cp.clients.restore', $client) }}" hidden>@csrf @if($client->is_active)<input type="hidden" name="_method" value="DELETE">@endif</form>

<script type="application/json" id="csData">{!! json_encode([
    'client' => ['id' => $client->id, 'title' => $title],
    'movements' => $movements,
    'balances' => $balances,
    'subscriptions' => $subscriptions,
    'lookups' => $lookups,
    'urls' => [
        'service' => route('cp.client-services.store'),
        'payment' => route('cp.payments.store', ['incoming']),
        'goods' => route('cp.client-goods-takes.store'),
        'memo' => route('cp.balance-memos.store'),
        'refund' => route('cp.payments.store', ['outgoing']),
        'pdf' => route('cp.clients.export-pdf', $client),
    ],
    'today' => now()->toDateString(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="{{ asset('assets/js/cp-date.js') }}?v={{ @filemtime(public_path('assets/js/cp-date.js')) }}"></script>
<script>
(function () {
    const D = JSON.parse(document.getElementById('csData').textContent);
    const L = D.lookups;
    const M = D.movements;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const num = (n) => Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const sym = (code) => (L.currencies.find((c) => c.code === code) || {}).symbol || code;
    const dayLabel = (d) => { if (!d) return ''; const [y, m, dd] = d.split('-').map(Number); return dd + '/' + m + '/' + y; };
    // عنوان اليوم بالحركات (12/11/2026): «اليوم · 12/11/2026»، «مبارح · …».
    const dayHead = (d) => { const t = new Date(D.today + 'T00:00:00'), y = new Date(t); y.setDate(t.getDate() - 1); const k = (x) => x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0'); return (d === D.today ? 'اليوم · ' : d === k(y) ? 'مبارح · ' : '') + dayLabel(d); };
    const iso = (dt) => dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
    const MOTION = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let view = 'list', cType = 'all', sub = new Set(), range = null, preset = 'all';

    /* ——— الرصيد ——— */
    function balanceBy(code) { return M.filter((m) => m.currency === code).reduce((a, m) => a + m.delta, 0); }
    function renderBalance() {
        const codes = [...new Set(M.map((m) => m.currency))];
        if (!codes.length) codes.push('ILS');
        // شريط الملخص (12/11/2026، أسلوب الأدراج): الرصيد كبير، وعلى اليسار عدد الحركات وآخر حركة.
        const big = codes.map((code) => {
            const v = balanceBy(code);
            const cls = Math.abs(v) < 0.005 ? 'zero' : (v > 0 ? 'due' : 'credit');
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
    function renderPresets() {
        $('csPresets').innerHTML = presets().map((p) => `<button type="button" data-p="${p[0]}" class="${preset === p[0] ? 'on' : ''}">${p[1]}</button>`).join('');
    }
    function rangeText() { return range ? (preset === 'custom' ? `${dayLabel(range.from)} – ${dayLabel(range.to)}` : range.label) : 'كل الوقت'; }
    $('csRangeBtn').addEventListener('click', (e) => { e.stopPropagation(); const menu = $('csRangeMenu'); const open = menu.hidden; closeMenus(); menu.hidden = !open; renderPresets(); });
    $('csPresets').addEventListener('click', (e) => {
        const b = e.target.closest('[data-p]'); if (!b) return;
        const p = presets().find((x) => x[0] === b.dataset.p);
        preset = p[0];
        range = p[2] ? { from: p[2], to: p[3], label: p[1] } : null;
        closeMenus(); render();
    });
    $('csApply').addEventListener('click', () => {
        let f = cpPeriod.iso($('csFrom')), t = cpPeriod.iso($('csTo'));
        if (!f && !t) return;
        if (!f) f = '1900-01-01';
        if (!t) t = D.today;
        if (f > t) [f, t] = [t, f];
        preset = 'custom';
        range = { from: f, to: t, label: 'مخصص' };
        closeMenus(); render();
    });

    /* ——— الفلاتر ——— */
    $('csType').addEventListener('click', (e) => {
        const b = e.target.closest('[data-t]'); if (!b) return;
        cType = b.dataset.t; sub = new Set();
        $('csType').querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b));
        render();
    });
    $('csChips').addEventListener('click', (e) => {
        const b = e.target.closest('[data-k]'); if (!b) return;
        const k = b.dataset.k;
        sub.has(k) ? sub.delete(k) : sub.add(k);
        render();
    });
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
        // Chronological running balance per currency over the whole history, then the period and type filters.
        const run = {};
        const all = M.map((m, i) => ({ ...m, i })).sort((a, b) => a.date.localeCompare(b.date) || a.i - b.i);
        all.forEach((m) => { run[m.currency] = (run[m.currency] || 0) + m.delta; m.after = run[m.currency]; });
        const before = {};
        const inRange = all.filter((m) => {
            if (range && m.date < range.from) { if (passesType(m)) before[m.currency] = (before[m.currency] || 0) + m.delta; return false; }
            if (range && m.date > range.to) return false;
            return passesType(m);
        });
        return { list: inRange, before };
    }
    // سطر العملة الأصلية (16/11/2026): «5 دولار بسعر 3.7» قبل الملاحظة.
    function subLine(m) { const t = [m.fx, m.notes].filter(Boolean).join(' · '); return t ? `<small>${esc(t)}</small>` : ''; }
    function amountCell(m) {
        const cls = m.delta > 0 ? 'due' : (m.delta < 0 ? 'credit' : 'zero');
        return `<span class="cl-amt ${cls}">${m.delta < 0 ? '−' : ''}${num(m.amount)} ${esc(m.symbol)}</span>`;
    }
    function balCell(v, code) {
        const cls = Math.abs(v) < 0.005 ? 'zero' : (v > 0 ? 'due' : 'credit');
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

        const totalIn = list.filter((m) => m.delta > 0).reduce((a, m) => a + m.delta, 0);
        const totalOut = list.filter((m) => m.delta < 0).reduce((a, m) => a - m.delta, 0);
        const beforeCodes = Object.keys(before).filter((c) => Math.abs(before[c]) >= 0.005);
        $('csSum').innerHTML = `<span>${list.length} حركة</span>`
            + (totalIn ? ` · <span>عليه <b class="due">${num(totalIn)}</b></span>` : '')
            + (totalOut ? ` · <span>دفع ونزل <b class="credit">${num(totalOut)}</b></span>` : '')
            + (beforeCodes.length ? ` · <span>رصيد سابق ${beforeCodes.map((c) => num(before[c]) + ' ' + esc(sym(c))).join('، ')}</span>` : '');

        if (view === 'list') {
            const ordered = list.slice().reverse();
            $('csTable').innerHTML = ordered.length ? `<div class="cs-row cs-hrow">${cType === 'all' ? '<span></span>' : ''}<span>الحركة</span><span>المبلغ</span><span>${filtered ? '' : 'الرصيد بعدها'}</span><span></span></div>` + ordered.map((m, i) => `${i === 0 || ordered[i - 1].date !== m.date ? `<div class="cs-day">${dayHead(m.date)}</div>` : ''}
                <div class="cs-row${cType === 'all' ? '' : ' no-ic'}" data-id="${esc(m.id)}">
                    ${cType === 'all' ? `<span class="cs-ic ${m.group}"><span class="material-symbols-outlined">${esc(m.icon)}</span></span>` : ''}
                    <span class="cs-t"><b>${esc(m.title)}</b>${subLine(m)}</span>
                    ${amountCell(m)}
                    ${filtered ? '<span></span>' : balCell(m.after, m.currency)}
                    <span class="cs-act">${m.edit ? `<button type="button" class="cl-more" aria-label="خيارات">⋯</button>
                        <div class="cl-menu" hidden><a href="${esc(m.edit)}">تعديل</a><hr><button type="button" class="danger" data-del="${esc(m.id)}">حذف</button></div>` : ''}</span>
                </div>`).join('')
                : `<p class="cl-empty">ما في حركات${range || filtered ? ' بهالفلتر' : ' لهالزبون لسا'}.</p>`;
        } else {
            let run = {};
            beforeCodes.forEach((c) => { run[c] = before[c]; });
            const head = `<div class="cs-st cs-hrow"><span>التاريخ</span><span>البيان</span><span>مدين<small>عليه</small></span><span>دائن<small>إله</small></span><span>${filtered ? 'المجموع' : 'الرصيد'}</span></div>`;
            const opening = range ? `<div class="cs-st cs-open"><span>${dayLabel(range.from)}</span><span>رصيد سابق</span><span></span><span></span><span>${beforeCodes.length ? beforeCodes.map((c) => num(before[c]) + ' ' + esc(sym(c))).join('<br>') : '0.00'}</span></div>` : '';
            let dr = 0, cr = 0;
            const body = list.map((m) => {
                run[m.currency] = (run[m.currency] || 0) + m.delta;
                if (m.delta > 0) dr += m.delta; else cr -= m.delta;
                return `<div class="cs-st"><span>${dayLabel(m.date)}</span><span>${esc(m.title)}${subLine(m)}</span><span>${m.delta > 0 ? num(m.delta) : ''}</span><span>${m.delta < 0 ? num(-m.delta) : ''}</span><span>${num(run[m.currency])} ${esc(sym(m.currency))}</span></div>`;
            }).join('');
            const foot = `<div class="cs-st cs-foot"><span></span><span>المجموع</span><span>${num(dr)}</span><span>${num(cr)}</span><span>${Object.keys(run).map((c) => num(run[c]) + ' ' + esc(sym(c))).join('<br>') || '0.00'}</span></div>`;
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
    {
        let html = '', last = null;
        L.serviceTypes.forEach((t) => {
            const g = t.group || '';
            if (g !== last) { if (g || last !== null) html += `<span class="cs-mgroup">${esc(g || 'بدون مجموعة')}</span>`; last = g; }
            html += `<button type="button" data-s="${t.id}">${esc(t.name)}</button>`;
        });
        $('mSvc').innerHTML = html + (html ? '<hr>' : '') + '<button type="button" class="cs-mfree" data-s="free">أخرى…</button>';
    }
    $('mPay').innerHTML = L.methods.map((p) => `<button type="button" data-m="${p.id}">${esc(p.name)}</button>`).join('');
    $('mSvc').addEventListener('click', (e) => {
        const b = e.target.closest('[data-s]'); if (!b) return;
        closeMenus();
        if (b.dataset.s === 'free') { openTx('service', { free: true }); return; }
        const t = L.serviceTypes.find((x) => x.id === +b.dataset.s);
        openTx('service', { typeId: t.id });
    });
    $('mPay').addEventListener('click', (e) => { const b = e.target.closest('[data-m]'); if (b) { closeMenus(); openTx('payment', { methodId: +b.dataset.m }); } });
    $('mOther').addEventListener('click', (e) => { const b = e.target.closest('[data-o]'); if (b) { closeMenus(); openTx(b.dataset.o, {}); } });

    $('csTable').addEventListener('click', (e) => {
        const b = e.target.closest('[data-del]'); if (!b) return;
        closeMenus();
        const m = M.find((x) => x.id === b.dataset.del);
        const after = balanceBy(m.currency) - m.delta;
        const msg = `حذف «${m.title}» بتاريخ ${dayLabel(m.date)}؟ الرصيد بعد الحذف: ${Math.abs(after) < 0.005 ? 'مسدّد' : (after > 0 ? 'عليه ' : 'إله ') + num(after) + ' ' + sym(m.currency)}.`;
        const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
        ask.then((ok) => { if (!ok) return; const f = $('csDelForm'); f.action = m.delete; f.submit(); });
    });
    const arch = $('csArchive') || $('csRestore');
    if (arch) arch.addEventListener('click', () => {
        closeMenus();
        if (arch.id === 'csRestore') { $('csArchForm').submit(); return; }
        const v = balanceBy('ILS');
        const msg = `أرشفة «${D.client.title}»؟` + (Math.abs(v) >= 0.005 ? ` رصيده ${v > 0 ? 'عليه' : 'إله'} ${num(v)} ₪ وبيضل محفوظ مع حركاته.` : '') + ' بيختفي من القوائم، وبتقدر تشوفه من الإعدادات ← العرض ← «إظهار المؤرشف».';
        const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
        ask.then((ok) => { if (ok) $('csArchForm').submit(); });
    });

    /* ——— نافذة الحركة ———
       نموذج المبلغ لكل النظام (قرار 17، 15/11/2026): العملة زر صغير جنب المبلغ، سعر الصرف سطر صغير بس مع عملة ثانية
       وافتراضه «السعر العالمي» من النظام، التلميح رمادي و«استخدمه» لحالها وردي، التاريخ بتقويم بس و«اليوم» لما يتغيّر،
       «رصيده هلأ» و«بعد…»، و«احفظ» باهت بدون مبلغ. نافذة الاشتراك (قرار 18): «عن شهر» لحاله وبدون خانة تاريخ. */
    const KIND = {
        service: { title: 'خدمة', url: D.urls.service, sign: 1, date: 'service_date', amtLabel: 'المبلغ', after: 'بعد الخدمة' },
        payment: { title: 'دفعة واردة', url: D.urls.payment, sign: -1, date: 'occurred_on', amtLabel: 'المبلغ', after: 'بعد الدفعة' },
        discount: { title: 'خصم', url: D.urls.goods, sign: -1, date: 'taken_on', amtLabel: 'قيمة الخصم', after: 'بعد الخصم' },
        goods: { title: 'دفعة بضاعة', url: D.urls.goods, sign: -1, date: 'taken_on', amtLabel: 'قيمة البضاعة', after: 'بعد البضاعة' },
        memo: { title: 'رصيد سابق', url: D.urls.memo, sign: 0, date: 'memo_date', amtLabel: 'المبلغ', after: 'بعدها' },
        refund: { title: 'استرداد', url: D.urls.refund, sign: 1, date: 'occurred_on', amtLabel: 'المبلغ المسترد', after: 'بعد الاسترداد' },
    };
    let tx = null;
    const hidden = (n, v) => `<input type="hidden" name="${n}" value="${esc(v)}">`;
    const N = (t) => `<bdi class="num" dir="ltr">${t}</bdi>`;
    const curOf = (id) => L.currencies.find((c) => +c.id === +id) || L.currencies.find((c) => c.code === 'ILS');
    const sysRate = (id) => +(L.rates || {})[id] || 0;
    function dateField(name) {
        return `<div class="cl-field"><label for="xDate">التاريخ</label>${cpDate.html({ id: 'xDate', name, today: dayLabel(D.today), label: 'التاريخ' })}`
            + '<small class="cl-err" id="xDateErr" hidden>التاريخ لازم يكون يوم/شهر/سنة</small></div>';
    }
    function moneyFields(label, memo) {
        const items = L.currencies.map((c) => `<button type="button" role="option" data-cur="${c.id}">${esc(c.name || c.code)} ${esc(c.symbol)}</button>`).join('');
        return `<div class="cl-field"><label for="xAmt">${label}</label>
            <div class="cp-amt"><input id="xAmt"${memo ? ' name="amount"' : ''} inputmode="decimal" dir="ltr" placeholder="0.00" autocomplete="off">
                <div class="cp-cur"><button type="button" class="cp-cur-b" id="xCurB" aria-haspopup="listbox" aria-label="العملة"><span id="xCurS"></span><span class="cp-cur-ar">▾</span></button>
                <div class="cp-cur-m" id="xCurM" role="listbox" hidden>${items}</div></div></div>
            <input type="hidden" name="currency_id" id="xCur" value="${L.ilsId}">
            ${memo ? '' : `<div class="cp-rate" id="xRateWrap" hidden><span>سعر الصرف</span><input id="xRate" name="exchange_rate" inputmode="decimal" dir="ltr" autocomplete="off"><span id="xIls"></span><small class="cp-rate-sys" id="xRateSys"></small></div>`}
            <small class="cp-hint" id="xHint"></small></div>`
            + (memo ? '' : '<input type="hidden" name="amount" id="xAmtIls"><input type="hidden" name="source_amount" id="xAmtSrc">');
    }
    const notesField = () => '<div class="cl-field"><label for="xNotes">ملاحظة</label><input id="xNotes" name="notes" placeholder="اختياري" autocomplete="off"></div>';
    function subOf(typeId) { return (D.subscriptions || []).find((x) => +x.type === +typeId) || null; }

    /* ——— الاشتراك ——— */
    const pkey = (p) => p[0] + '-' + String(p[1] + 1).padStart(2, '0');
    const plabel = (p) => (p[1] + 1) + '/' + p[0];
    const padd = (p, k) => { const d = new Date(p[0], p[1] + k, 1); return [d.getFullYear(), d.getMonth()]; };
    const pcmp = (a, b) => a[0] * 12 + a[1] - (b[0] * 12 + b[1]);
    const pdim = (p) => new Date(p[0], p[1] + 1, 0).getDate();
    function nowP() { const t = new Date(D.today + 'T00:00:00'); return [t.getFullYear(), t.getMonth()]; }
    function startP(s) { const [y, m] = s.from.split('-').map(Number); return [y, m - 1]; }
    function firstMissing(s) { let p = startP(s); const n = nowP(); while (pcmp(p, n) <= 0) { if (!s.added[pkey(p)]) return p; p = padd(p, 1); } return n; }
    function missedOf(s, cur) { const out = []; let p = startP(s); const n = nowP(); while (pcmp(p, n) < 0) { if (!s.added[pkey(p)] && pcmp(p, cur) !== 0) out.push(p); p = padd(p, 1); } return out; }
    const isFirst = () => { const s = tx.sub; return !Object.keys(s.added).length && pcmp(tx.p, startP(s)) <= 0; };
    function subDays() { const n = pdim(tx.p); return (tx.stop ? tx.d2 : n) - (isFirst() ? tx.d1 : 1) + 1; }
    function subSugg() { const n = pdim(tx.p), d = subDays(); return d >= n ? tx.sub.amount : Math.round(tx.sub.amount / n * d * 100) / 100; }
    function subNote() {
        const p = tx.p, f = isFirst(), s = tx.stop;
        const parts = [(s ? (f ? 'أول وآخر شهر · ' : 'آخر شهر · ') : '') + 'اشتراك ' + plabel(p)];
        if (f && tx.d1 > 1) parts.push('من ' + tx.d1 + '/' + plabel(p));
        if (s) parts.push('لغاية ' + tx.d2 + '/' + plabel(p));
        if (subDays() < pdim(p)) parts.push(subDays() + ' يوم من ' + pdim(p));
        if (s) parts.push('وقف الاشتراك');
        return parts.join(' · ');
    }
    function subBar() {
        const s = tx.sub, d = s.added[pkey(tx.p)], ms = missedOf(s, tx.p);
        return `<div class="cs-sbrow"><b>اشتراك شهري · ${N(num(s.amount) + ' ₪')}</b>${ms.length ? `<span class="cs-miss">فاتك ${ms.map((p) => `<button type="button" data-gm="${pkey(p)}">${N(plabel(p))}</button>`).join('')}</span>` : ''}</div>
            <div class="cs-sbrow"><span class="cs-mpick"><span>عن شهر</span><button type="button" class="cs-mar" data-mv="-1" aria-label="الشهر اللي قبل">›</button><b>${N(plabel(tx.p))}</b><button type="button" class="cs-mar" data-mv="1" aria-label="الشهر اللي بعد">‹</button></span>
            <span class="cs-pill${d ? ' ok' : ''}">${d ? 'انضاف ✓ بتاريخ ' + N(dayLabel(d)) : (isFirst() ? 'أول شهر' : 'لسا ما انضاف')}</span></div>`;
    }
    function subDayFields() {
        const p = tx.p, n = pdim(p), lock = pkey(p);
        if (!isFirst() && !tx.stop) return `<p class="cs-autod">بينسجل بتاريخ ${N(dayLabel(lock + '-' + String(n).padStart(2, '0')))} (آخر يوم بالشهر)</p>`;
        const f = (id, lab, v, data) => `<div class="cl-field"><label for="${id}">${lab}</label>${cpDate.html({ id, value: v + '/' + plabel(p), today: dayLabel(D.today), data: { lock, ...data }, label: lab })}</div>`;
        let h = '';
        if (isFirst()) h += f('xD1', 'بلّشنا معه يوم', tx.d1, { 'min-day': 1, 'max-day': tx.stop ? tx.d2 : n, 'range-to': tx.stop ? tx.d2 : n });
        if (tx.stop) h += f('xD2', 'آخر يوم استخدم الخدمة', tx.d2, { 'min-day': isFirst() ? tx.d1 : 1, 'max-day': n, 'range-from': isFirst() ? tx.d1 : 1 });
        const at = tx.stop ? tx.d2 + '/' + plabel(p) + ' (آخر يوم استخدمها)' : n + '/' + plabel(p) + ' (آخر يوم بالشهر)';
        return (isFirst() && tx.stop ? `<div class="cs-two">${h}</div>` : h) + `<p class="cs-autod">بينسجل بتاريخ ${N(at.split(' ')[0])} ${at.slice(at.indexOf(' ') + 1)} · ${subDays()} يوم من ${n}</p>`;
    }
    function subReset(keepAmt) {
        const n = pdim(tx.p), np = nowP();
        tx.d1 = 1; tx.d2 = pcmp(tx.p, np) === 0 ? new Date(D.today + 'T00:00:00').getDate() : n;
        if (tx.d2 < tx.d1) tx.d2 = tx.d1;
        if (!keepAmt) tx.manual = false;
    }
    function subPaint(full) {
        $('xSubBar').innerHTML = subBar();
        $('xDays').innerHTML = subDayFields();
        const d = tx.sub.added[pkey(tx.p)];
        $('txDup').hidden = !d;
        if (d) $('txDupTxt').textContent = `شهر ${plabel(tx.p)} انضاف قبل لهالخدمة. متأكد بدك تضيفه مرة ثانية؟`;
        if (!tx.manual && curOf($('xCur').value).code === 'ILS') $('xAmt').value = String(subSugg());
        if (tx.noteAuto) $('xNotes').value = subNote();
        $('xPeriod').value = pkey(tx.p);
        $('xStartDay').value = isFirst() ? tx.d1 : '';
        $('xEndDay').value = tx.stop ? tx.d2 : '';
        $('xStopIn').value = tx.stop ? 1 : 0;
        $('xAgain').value = d ? 1 : 0;
        $('xStopNote').hidden = !tx.stop;
        void full;
        check();
    }

    function openTx(kind, opt) {
        const K = KIND[kind];
        tx = { kind, opt };
        let title = K.title, fields = '', hid = '', action = K.url;
        const partyKey = 'client:' + D.client.id;
        if (kind === 'service' && opt.free) {
            title = 'خدمة · أخرى';
            hid = hidden('client_id', D.client.id);
            fields = '<div class="cl-field"><label for="xWhat">شو الخدمة</label><input id="xWhat" name="title" maxlength="255" placeholder="عنوان لهالزبون بس"></div>'
                + moneyFields(K.amtLabel) + dateField(K.date) + notesField();
        } else if (kind === 'service') {
            const t = L.serviceTypes.find((x) => x.id === opt.typeId);
            title = 'خدمة · ' + t.name;
            tx.type = t;
            tx.sub = subOf(t.id);
            if (tx.sub) {
                action = tx.sub.url;
                tx.p = firstMissing(tx.sub); tx.stop = false; tx.noteAuto = true; subReset();
                hid = '<input type="hidden" name="period" id="xPeriod"><input type="hidden" name="start_day" id="xStartDay"><input type="hidden" name="end_day" id="xEndDay"><input type="hidden" name="stop" id="xStopIn" value="0"><input type="hidden" name="again" id="xAgain" value="0">';
                fields = '<div class="cs-subbar" id="xSubBar"></div>' + moneyFields(K.amtLabel) + '<div id="xDays"></div>' + notesField()
                    + '<label class="cs-stopt"><input type="checkbox" id="xStop"> هادا آخر شهر، الزبون وقّف الخدمة</label>'
                    + `<p class="cs-autod" id="xStopNote" hidden>بعد الحفظ بتنشال «${esc(t.name)}» من اشتراكاته، والحركات القديمة بتضل.</p>`;
            } else {
                hid = hidden('client_id', D.client.id) + hidden('service_type_id', t.id);
                fields = (t.phases ? '<div class="cl-field"><label>نوع الشغل <small>(اختياري)</small></label><div class="cl-seg cs-phase" id="xPhase">'
                    + [['both', 'تصميم وتنفيذ'], ['design', 'تصميم'], ['execution', 'تنفيذ']].map(([v, l]) => `<button type="button" data-p="${v}">${l}</button>`).join('')
                    + '</div><input type="hidden" name="work_phase" id="xPhaseIn" value=""></div>' : '')
                    + moneyFields(K.amtLabel) + dateField(K.date) + notesField();
            }
        } else if (kind === 'refund') {
            const opts = L.methods.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
            hid = hidden('party_key', partyKey) + hidden('fund_id', L.fundId) + hidden('name', D.client.title) + hidden('kind', 'refund');
            fields = `<div class="cl-field"><label for="xMethod">طلع من</label><select id="xMethod" name="payment_method_id" class="cs-sel">${opts}</select></div>`
                + moneyFields(K.amtLabel) + dateField(K.date) + notesField();
        } else if (kind === 'payment') {
            const p = L.methods.find((x) => x.id === opt.methodId);
            title = 'دفعة · ' + p.name;
            hid = hidden('party_key', partyKey) + hidden('fund_id', L.fundId) + hidden('payment_method_id', p.id) + hidden('name', D.client.title);
            fields = moneyFields(K.amtLabel) + dateField(K.date) + notesField();
        } else if (kind === 'discount' || kind === 'goods') {
            hid = hidden('party_key', partyKey) + hidden('party_type', 'client') + hidden('party_id', D.client.id) + hidden('category', kind === 'goods' ? 'other' : 'discount');
            fields = (kind === 'goods' ? '<div class="cl-field"><label for="xWhat">شو أخدنا</label><input id="xWhat" name="title" maxlength="255" placeholder="مثلاً: كرتونة مياه"></div>' : '')
                + moneyFields(K.amtLabel) + dateField(K.date) + notesField();
        } else if (kind === 'memo') {
            hid = hidden('party_key', partyKey) + hidden('party_type', 'client') + hidden('party_id', D.client.id) + hidden('reason', 'opening');
            fields = `<div class="cl-field"><label>الرصيد</label><div class="cl-seg cs-dir" id="xDir">
                    <button type="button" class="on" data-d="owed_to_us">عليه</button><button type="button" data-d="owed_by_us">إله</button></div>
                    <input type="hidden" name="direction" id="xDirIn" value="owed_to_us"></div>`
                + moneyFields(K.amtLabel, true) + dateField(K.date) + notesField();
        }
        $('txTitle').textContent = title;
        $('txHidden').innerHTML = hid;
        $('txFields').innerHTML = fields;
        $('txForm').action = action;
        $('txSave').disabled = true;
        $('txSave').textContent = 'احفظ';
        $('txSheet').hidden = false;
        wireTx();
        if (tx.sub) subPaint(true);
        setTimeout(() => { const f = $('xWhat') || $('xAmt'); f.focus(); if (tx && tx.sub) f.select(); }, 30);
    }
    function setCur(id) {
        const c = curOf(id);
        $('xCur').value = c.id;
        $('xCurS').textContent = c.symbol;
        $('xCurM').querySelectorAll('[data-cur]').forEach((b) => b.classList.toggle('on', +b.dataset.cur === +c.id));
        const w = $('xRateWrap');
        if (w) {
            const fx = c.code !== 'ILS';
            w.hidden = !fx;
            if (fx) { $('xRate').value = sysRate(c.id) || ''; $('xRate').previousElementSibling.textContent = 'سعر صرف ' + ({ USD: 'الدولار', JOD: 'الدينار' }[c.code] || c.code); }
        }
    }
    function paintHint() {
        const h = $('xHint');
        if (!h || !tx) return;
        const ils = curOf($('xCur').value).code === 'ILS';
        const a = parseFloat($('xAmt').value) || 0;
        let html = '';
        if (tx.sub) {
            const s = subSugg(), n = pdim(tx.p), d = subDays(), part = d < n;
            const same = ils && Math.abs(a - s) < 0.005;
            const calc = part ? `${N(num(tx.sub.amount))} ÷ ${n} يوم × ${d} يوم = ${N(num(s) + ' ₪')}` : `مبلغ الاشتراك: ${N(num(tx.sub.amount) + ' ₪')}`;
            html = same ? (part ? 'الشهر مش كامل: ' + calc : '') : `${part ? 'المقترح: ' : ''}${calc} · <button type="button" class="cp-use" data-use="${s}">استخدمه</button>`;
        } else if (tx.type) {
            const v = tx.type.last ?? tx.type.price;
            if (v != null && !(ils && Math.abs(a - v) < 0.005)) html = `${tx.type.last != null ? 'آخر مرة لهالزبون' : 'السعر المعتاد'}: ${N(num(v) + ' ₪')} · <button type="button" class="cp-use" data-use="${v}">استخدمه</button>`;
        } else if (tx.kind === 'payment') {
            const v = balanceBy('ILS');
            if (v > 0.005 && !(ils && Math.abs(a - v) < 0.005)) html = `عليه ${N(num(v) + ' ₪')} · <button type="button" class="cp-use" data-use="${v.toFixed(2)}">عبّي الكل</button>`;
        }
        h.innerHTML = html;
    }
    function wireTx() {
        setCur(L.ilsId);
        const ph = $('xPhase');
        if (ph) ph.addEventListener('click', (e) => { const b = e.target.closest('[data-p]'); if (!b) return; const off = b.classList.contains('on'); ph.querySelectorAll('button').forEach((x) => x.classList.toggle('on', !off && x === b)); $('xPhaseIn').value = off ? '' : b.dataset.p; });
        const dir = $('xDir');
        if (dir) dir.addEventListener('click', (e) => { const b = e.target.closest('[data-d]'); if (!b) return; dir.querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b)); $('xDirIn').value = b.dataset.d; check(); });
        const F = $('txFields');
        F.addEventListener('click', (e) => {
            if (e.target.closest('#xCurB')) { e.stopPropagation(); $('xCurM').hidden = !$('xCurM').hidden; return; }
            const c = e.target.closest('[data-cur]');
            if (c) { $('xCurM').hidden = true; setCur(c.dataset.cur); if (tx.sub) tx.manual = true; check(); $('xAmt').focus(); return; }
            const u = e.target.closest('[data-use]');
            if (u) { setCur(L.ilsId); $('xAmt').value = u.dataset.use; if (tx.sub) tx.manual = false; check(); $('xAmt').focus(); return; }
            if (e.target.closest('[data-rate-sys]')) { $('xRate').value = sysRate($('xCur').value); check(); return; }
            if (!tx.sub) return;
            const mv = e.target.closest('[data-mv]');
            if (mv) { tx.p = padd(tx.p, +mv.dataset.mv); subReset(); subPaint(); return; }
            const gm = e.target.closest('[data-gm]');
            if (gm) { const [y, m] = gm.dataset.gm.split('-').map(Number); tx.p = [y, m - 1]; subReset(); subPaint(); }
        });
        F.addEventListener('change', (e) => { if (e.target.id === 'xStop') { tx.stop = e.target.checked; const d1 = tx.d1; subReset(); tx.d1 = d1; if (tx.d2 < tx.d1) tx.d2 = tx.d1; subPaint(); } });
        F.addEventListener('input', (e) => {
            const id = e.target.id;
            if (tx.sub && (id === 'xD1' || id === 'xD2')) {
                const p = cpDate.parse(e.target.value);
                if (p && p[0] === tx.p[0] && p[1] === tx.p[1]) {
                    if (id === 'xD1') tx.d1 = Math.min(tx.stop ? tx.d2 : pdim(tx.p), p[2]); else tx.d2 = Math.max(isFirst() ? tx.d1 : 1, p[2]);
                    const keep = document.activeElement && document.activeElement.id;
                    const sel = keep === id ? [e.target.selectionStart, e.target.selectionEnd] : null;
                    subPaint();
                    if (sel) { const el = $(id); el.focus(); el.setSelectionRange(sel[0], sel[1]); }
                }
                return;
            }
            if (tx.sub && id === 'xAmt') tx.manual = true;
            if (tx.sub && id === 'xNotes') tx.noteAuto = false;
            check();
        });
        F.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && ['xAmt', 'xRate', 'xDate', 'xD1', 'xD2'].includes(e.target.id)) { e.preventDefault(); if (!$('txSave').disabled) $('txForm').requestSubmit(); }
        });
    }
    document.addEventListener('click', (e) => { const m = $('xCurM'); if (m && !m.hidden && !e.target.closest('.cp-cur')) m.hidden = true; });
    function parseDay(v) {
        const p = cpDate.parse(v);
        return p ? iso(new Date(p[0], p[1], p[2])) : null;
    }
    function ilsAmount() {
        const a = parseFloat($('xAmt').value);
        if (!(a > 0)) return 0;
        const code = curOf($('xCur').value).code;
        if (code === 'ILS' || tx.kind === 'memo') return a;
        return a * (parseFloat(($('xRate') || {}).value) || 0);
    }
    function check() {
        if (!tx || !$('xCur')) return;
        const c = curOf($('xCur').value);
        const fx = c.code !== 'ILS' && tx.kind !== 'memo';
        const a = parseFloat($('xAmt').value);
        const ils = ilsAmount();
        const day = tx.sub ? true : parseDay($('xDate').value);
        if ($('xDateErr')) $('xDateErr').hidden = !!day || $('xDate').value.length < 6;
        if (tx.kind !== 'memo') {
            $('xAmtIls').value = fx ? '' : ($('xAmt').value || '');
            $('xAmtSrc').value = fx ? ($('xAmt').value || '') : '';
            if ($('xIls')) $('xIls').innerHTML = fx && ils ? '= <b>' + N(num(ils) + ' ₪') + '</b>' : '';
            if ($('xRateSys')) {
                const sr = sysRate(c.id), cur = parseFloat($('xRate').value);
                $('xRateSys').innerHTML = !sr || Math.abs(cur - sr) < 1e-9 ? 'السعر العالمي' : '<button type="button" class="cp-use" data-rate-sys="1" title="رجّع السعر العالمي">↺ السعر العالمي</button>';
            }
        }
        let sign = KIND[tx.kind].sign;
        if (tx.kind === 'memo') sign = $('xDirIn').value === 'owed_to_us' ? 1 : -1;
        const cur = tx.kind === 'memo' ? c.code : 'ILS';
        const nowV = balanceBy(cur), after = nowV + sign * ils;
        const box = (lab, v) => { const cls = Math.abs(v) < 0.005 ? 'zero' : (v > 0 ? 'due' : 'credit'); return `<span class="cs-bl"><span>${lab}</span><b class="${cls}">${cls === 'zero' ? 'مسدّد' : N(num(v) + ' ' + esc(sym(cur)))}</b></span>`; };
        $('txAfter').innerHTML = box('رصيده هلأ', nowV) + (ils ? box(KIND[tx.kind].after, after) : '');
        paintHint();
        // تنبيه التكرار: نفس النوع ونفس المبلغ خلال ±3 أيام (الاشتراك إله تنبيه الشهر بداله).
        if (!tx.sub) {
            let dup = null;
            if (ils && day) {
                const group = { service: 'svc', payment: 'pay' }[tx.kind] || 'other';
                const dd = new Date(day + 'T00:00:00');
                dup = M.find((m) => m.group === group && Math.abs(Math.abs(m.delta) - ils) < 0.005
                    && Math.abs((new Date(m.date + 'T00:00:00') - dd) / 864e5) <= 3);
            }
            $('txDup').hidden = !dup;
            if (dup) $('txDupTxt').textContent = `في حركة بنفس المبلغ بتاريخ ${dayLabel(dup.date)}: ${dup.title}. تأكد إنها مش مكررة.`;
        } else {
            const d = tx.sub.added[pkey(tx.p)];
            $('txSave').textContent = tx.stop ? 'أضف آخر شهر ووقّف' : (d ? 'أضيفه مرة ثانية' : 'أضف شهر ' + plabel(tx.p));
        }
        const okWhat = !$('xWhat') || $('xWhat').value.trim().length > 0;
        const okRate = !fx || parseFloat(($('xRate') || {}).value) > 0;
        $('txSave').disabled = !(a > 0 && day && okWhat && okRate);
    }
    function closeTx() { $('txSheet').hidden = true; tx = null; }
    $('txCancel').addEventListener('click', closeTx);
    $('txSheet').addEventListener('click', (e) => { if (e.target.id === 'txSheet') closeTx(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('txSheet').hidden) closeTx(); });
    $('txForm').addEventListener('submit', (e) => {
        if ($('txSave').disabled) { e.preventDefault(); return; }
        if (tx && tx.sub) return;
        const day = parseDay($('xDate').value);
        $('xDate').value = dayLabel(day);
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
    render();
    /* ——— التسجيل السريع: فتح نفس نافذة الحركة (§11) ——— */
    {
        const qk = new URLSearchParams(location.search).get('quick');
        const menu = (id) => setTimeout(() => document.querySelector(`[data-menu=${id}]`).click(), 60);
        if (qk === 'in') menu('mPay');
        else if (qk === 'out') openTx('refund', {});
        else if (qk === 'take') openTx('goods', {});
        else if (qk === 'give') openTx('service', { free: true });
        if (qk) history.replaceState(null, '', location.pathname);
    }
})();
</script>
@endsection
