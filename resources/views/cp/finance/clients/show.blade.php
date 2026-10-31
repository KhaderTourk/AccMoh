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
                </div>
            </div>
            <div class="cs-add">
                <button type="button" class="cl-more" data-menu="mClient" aria-label="خيارات الزبون">⋯</button>
                <div class="cl-menu cs-menu" id="mClient" hidden>
                    <a href="{{ route('cp.clients.edit', $client) }}">تعديل بيانات الزبون</a>
                    <hr>
                    @if($client->is_active)
                        <button type="button" class="danger" id="csArchive">أرشفة الزبون</button>
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
        <div class="cs-range">
            <button type="button" class="cs-range-btn" id="csRangeBtn"><span class="material-symbols-outlined">calendar_month</span><span id="csRangeTxt">كل الوقت</span></button>
            <div class="cl-menu cs-range-menu" id="csRangeMenu" hidden>
                <div id="csPresets"></div>
                <hr>
                <div class="cs-custom">
                    <label>من <input type="date" id="csFrom"></label>
                    <label>لغاية <input type="date" id="csTo"></label>
                    <button type="button" class="cp-btn cp-btn-primary" id="csApply">تطبيق</button>
                </div>
            </div>
        </div>
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
    'lookups' => $lookups,
    'urls' => [
        'service' => route('cp.client-services.store'),
        'payment' => route('cp.payments.store', ['incoming']),
        'goods' => route('cp.client-goods-takes.store'),
        'memo' => route('cp.balance-memos.store'),
        'pdf' => route('cp.clients.export-pdf', $client),
    ],
    'today' => now()->toDateString(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
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
    const iso = (dt) => dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
    const MOTION = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let view = 'list', cType = 'all', sub = new Set(), range = null, preset = 'all';

    /* ——— الرصيد ——— */
    function balanceBy(code) { return M.filter((m) => m.currency === code).reduce((a, m) => a + m.delta, 0); }
    function renderBalance() {
        const codes = [...new Set(M.map((m) => m.currency))];
        if (!codes.length) codes.push('ILS');
        $('csBalance').innerHTML = codes.map((code) => {
            const v = balanceBy(code);
            const cls = Math.abs(v) < 0.005 ? 'zero' : (v > 0 ? 'due' : 'credit');
            const word = cls === 'zero' ? 'مسدّد' : (cls === 'due' ? 'عليه' : 'إله (مقدّماً)');
            return `<div class="cs-bal ${cls}"><span>${word}</span><b>${cls === 'zero' ? '0.00' : num(v)} ${esc(sym(code))}</b></div>`;
        }).join('');
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
    function rangeText() { return range ? `${range.label} · ${dayLabel(range.from)} – ${dayLabel(range.to)}` : 'كل الوقت'; }
    $('csRangeBtn').addEventListener('click', (e) => { e.stopPropagation(); const menu = $('csRangeMenu'); const open = menu.hidden; closeMenus(); menu.hidden = !open; renderPresets(); });
    $('csPresets').addEventListener('click', (e) => {
        const b = e.target.closest('[data-p]'); if (!b) return;
        const p = presets().find((x) => x[0] === b.dataset.p);
        preset = p[0];
        range = p[2] ? { from: p[2], to: p[3], label: p[1] } : null;
        closeMenus(); render();
    });
    $('csApply').addEventListener('click', () => {
        let f = $('csFrom').value, t = $('csTo').value;
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
            $('csTable').innerHTML = ordered.length ? `<div class="cs-row cs-hrow"><span>التاريخ</span>${cType === 'all' ? '<span></span>' : ''}<span>الحركة</span><span>المبلغ</span><span>${filtered ? '' : 'الرصيد بعدها'}</span><span></span></div>` + ordered.map((m) => `
                <div class="cs-row${cType === 'all' ? '' : ' no-ic'}" data-id="${esc(m.id)}">
                    <span class="cs-date">${dayLabel(m.date)}</span>
                    ${cType === 'all' ? `<span class="cs-ic ${m.group}"><span class="material-symbols-outlined">${esc(m.icon)}</span></span>` : ''}
                    <span class="cs-t"><b>${esc(m.title)}</b>${m.notes ? `<small>${esc(m.notes)}</small>` : ''}</span>
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
                return `<div class="cs-st"><span>${dayLabel(m.date)}</span><span>${esc(m.title)}${m.notes ? `<small>${esc(m.notes)}</small>` : ''}</span><span>${m.delta > 0 ? num(m.delta) : ''}</span><span>${m.delta < 0 ? num(-m.delta) : ''}</span><span>${num(run[m.currency])} ${esc(sym(m.currency))}</span></div>`;
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
    $('mSvc').innerHTML = L.serviceTypes.length
        ? L.serviceTypes.map((t) => `<button type="button" data-s="${t.id}">${esc(t.name)}</button>`).join('')
        : '<p class="cs-mnote">ما في خدمات مفعّلة. ضيفها من صفحة الخدمات.</p>';
    $('mPay').innerHTML = L.methods.map((p) => `<button type="button" data-m="${p.id}">${esc(p.name)}</button>`).join('');
    $('mSvc').addEventListener('click', (e) => { const b = e.target.closest('[data-s]'); if (b) { closeMenus(); openTx('service', { typeId: +b.dataset.s }); } });
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
        const msg = `أرشفة «${D.client.title}»؟` + (Math.abs(v) >= 0.005 ? ` رصيده ${v > 0 ? 'عليه' : 'إله'} ${num(v)} ₪ وبيضل محفوظ مع حركاته.` : '') + ' بتلاقيه بتاب «مؤرشف».';
        const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
        ask.then((ok) => { if (ok) $('csArchForm').submit(); });
    });

    /* ——— نافذة الحركة ——— */
    const KIND = {
        service: { title: 'خدمة', url: D.urls.service, sign: 1, date: 'service_date', amtLabel: 'السعر' },
        payment: { title: 'دفعة واردة', url: D.urls.payment, sign: -1, date: 'occurred_on', amtLabel: 'المبلغ' },
        discount: { title: 'خصم', url: D.urls.goods, sign: -1, date: 'taken_on', amtLabel: 'قيمة الخصم' },
        goods: { title: 'دفعة بضاعة', url: D.urls.goods, sign: -1, date: 'taken_on', amtLabel: 'قيمة البضاعة' },
        memo: { title: 'رصيد سابق', url: D.urls.memo, sign: 0, date: 'memo_date', amtLabel: 'المبلغ' },
    };
    let tx = null;
    const hidden = (n, v) => `<input type="hidden" name="${n}" value="${esc(v)}">`;
    function dateField(name) {
        return `<div class="cl-field"><label for="xDate">التاريخ</label><div class="cs-date-in">
            <input id="xDate" name="${name}" inputmode="numeric" dir="ltr" placeholder="يوم/شهر/سنة" value="${dayLabel(D.today)}">
            <button type="button" class="cs-today" id="xToday">اليوم</button>
            <label class="cs-cal" title="اختار من التقويم"><span class="material-symbols-outlined">calendar_month</span><input type="date" id="xPick" tabindex="-1"></label>
        </div><small class="cl-err" id="xDateErr" hidden>التاريخ لازم يكون يوم/شهر/سنة</small></div>`;
    }
    function moneyFields(label) {
        const cur = L.currencies.map((c) => `<option value="${c.id}" data-code="${esc(c.code)}">${esc(c.code)} ${esc(c.symbol)}</option>`).join('');
        return `<div class="cs-two"><div class="cl-field"><label for="xAmt">${label}</label><input id="xAmt" inputmode="decimal" dir="ltr" placeholder="0.00"><small class="cs-hint" id="xHint"></small></div>
            <div class="cl-field"><label for="xCur">العملة</label><select id="xCur" name="currency_id">${cur}</select></div></div>
            <div class="cl-field" id="xRateWrap" hidden><label for="xRate">سعر الصرف للشيكل</label><input id="xRate" name="exchange_rate" inputmode="decimal" dir="ltr"><small class="cs-hint" id="xIls"></small></div>
            <input type="hidden" name="amount" id="xAmtIls"><input type="hidden" name="source_amount" id="xAmtSrc">`;
    }
    function openTx(kind, opt) {
        const K = KIND[kind];
        tx = { kind, opt };
        let title = K.title, fields = '', hid = '';
        const partyKey = 'client:' + D.client.id;
        if (kind === 'service') {
            const t = L.serviceTypes.find((x) => x.id === opt.typeId);
            title = 'خدمة · ' + t.name;
            hid = hidden('client_id', D.client.id) + hidden('service_type_id', t.id);
            fields = moneyFields(K.amtLabel) + dateField(K.date);
        } else if (kind === 'payment') {
            const p = L.methods.find((x) => x.id === opt.methodId);
            title = 'دفعة · ' + p.name;
            hid = hidden('party_key', partyKey) + hidden('fund_id', L.fundId) + hidden('payment_method_id', p.id) + hidden('name', D.client.title);
            fields = moneyFields(K.amtLabel) + dateField(K.date);
        } else if (kind === 'discount' || kind === 'goods') {
            hid = hidden('party_key', partyKey) + hidden('party_type', 'client') + hidden('party_id', D.client.id) + hidden('category', kind === 'goods' ? 'other' : 'discount');
            fields = (kind === 'goods' ? '<div class="cl-field"><label for="xWhat">شو أخدنا</label><input id="xWhat" name="title" maxlength="255" placeholder="مثلاً: كرتونة مياه"></div>' : '')
                + moneyFields(K.amtLabel) + dateField(K.date);
        } else if (kind === 'memo') {
            hid = hidden('party_key', partyKey) + hidden('party_type', 'client') + hidden('party_id', D.client.id) + hidden('reason', 'opening');
            fields = `<div class="cl-field"><label>الرصيد</label><div class="cl-seg cs-dir" id="xDir">
                    <button type="button" class="on" data-d="owed_to_us">عليه</button><button type="button" data-d="owed_by_us">إله</button></div>
                    <input type="hidden" name="direction" id="xDirIn" value="owed_to_us"></div>`
                + `<div class="cs-two"><div class="cl-field"><label for="xAmt">المبلغ</label><input id="xAmt" name="amount" inputmode="decimal" dir="ltr" placeholder="0.00"></div>
                    <div class="cl-field"><label for="xCur">العملة</label><select id="xCur" name="currency_id">${L.currencies.map((c) => `<option value="${c.id}" data-code="${esc(c.code)}">${esc(c.code)} ${esc(c.symbol)}</option>`).join('')}</select></div></div>`
                + dateField(K.date);
        }
        fields += '<div class="cl-field"><label for="xNotes">ملاحظات</label><textarea id="xNotes" name="notes" rows="2" placeholder="اختياري"></textarea></div>';
        $('txTitle').textContent = title;
        $('txHidden').innerHTML = hid;
        $('txFields').innerHTML = fields;
        $('txForm').action = K.url;
        $('txSave').disabled = true;
        $('txSheet').hidden = false;
        wireTx();
        setTimeout(() => ($('xWhat') || $('xAmt')).focus(), 30);
    }
    function wireTx() {
        const cur = $('xCur');
        const curCode = () => cur.selectedOptions[0].dataset.code;
        if (tx.kind === 'service') {
            const t = L.serviceTypes.find((x) => x.id === tx.opt.typeId);
            if (t.last) $('xHint').textContent = 'آخر سعر لهالزبون: ' + num(t.last);
        }
        if (tx.kind === 'payment') {
            const v = balanceBy('ILS');
            if (v > 0.005) $('xHint').innerHTML = `عليه ${num(v)} ₪ · <button type="button" class="cs-fill" id="xFill">عبّي الكل</button>`;
        }
        const dir = $('xDir');
        if (dir) dir.addEventListener('click', (e) => { const b = e.target.closest('[data-d]'); if (!b) return; dir.querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b)); $('xDirIn').value = b.dataset.d; check(); });
        cur.addEventListener('change', () => {
            const fx = curCode() !== 'ILS';
            if ($('xRateWrap')) { $('xRateWrap').hidden = !fx; if (fx && !$('xRate').value) { const r = L.rates[cur.value]; if (r) $('xRate').value = r; } }
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
        if (code === 'ILS' || tx.kind === 'memo') return a;
        return a * (parseFloat(($('xRate') || {}).value) || 0);
    }
    function check() {
        const code = $('xCur').selectedOptions[0].dataset.code;
        const fx = code !== 'ILS' && tx.kind !== 'memo';
        const a = parseFloat($('xAmt').value);
        const ils = ilsAmount();
        const day = parseDay($('xDate').value);
        $('xDateErr').hidden = !!day || $('xDate').value.length < 6;
        if (tx.kind !== 'memo') {
            $('xAmtIls').value = fx ? '' : ($('xAmt').value || '');
            $('xAmtSrc').value = fx ? ($('xAmt').value || '') : '';
            if (fx && ils) $('xIls').textContent = '= ' + num(ils) + ' ₪';
        }
        let sign = KIND[tx.kind].sign;
        if (tx.kind === 'memo') sign = $('xDirIn').value === 'owed_to_us' ? 1 : -1;
        const cur = tx.kind === 'memo' ? code : 'ILS';
        const after = balanceBy(cur) + sign * ils;
        $('txAfter').innerHTML = ils ? 'الرصيد بعدها: ' + balCell(after, cur) : '';
        // تنبيه التكرار: نفس النوع ونفس المبلغ خلال ±3 أيام
        let dup = null;
        if (ils && day) {
            const group = { service: 'svc', payment: 'pay' }[tx.kind] || 'other';
            const dd = new Date(day + 'T00:00:00');
            dup = M.find((m) => m.group === group && Math.abs(Math.abs(m.delta) - ils) < 0.005
                && Math.abs((new Date(m.date + 'T00:00:00') - dd) / 864e5) <= 3);
        }
        $('txDup').hidden = !dup;
        if (dup) $('txDupTxt').textContent = `في حركة بنفس المبلغ بتاريخ ${dayLabel(dup.date)}: ${dup.title}. تأكد إنها مش مكررة.`;
        const okWhat = tx.kind !== 'goods' || ($('xWhat').value.trim().length > 0);
        const okRate = !fx || parseFloat(($('xRate') || {}).value) > 0;
        $('txSave').disabled = !(a > 0 && day && okWhat && okRate);
    }
    function closeTx() { $('txSheet').hidden = true; tx = null; }
    $('txCancel').addEventListener('click', closeTx);
    $('txSheet').addEventListener('click', (e) => { if (e.target.id === 'txSheet') closeTx(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('txSheet').hidden) closeTx(); });
    $('txForm').addEventListener('submit', (e) => {
        if ($('txSave').disabled) { e.preventDefault(); return; }
        const day = parseDay($('xDate').value);
        $('xDate').value = dayLabel(day);
    });

    renderBalance();
    render();
})();
</script>
@endsection
