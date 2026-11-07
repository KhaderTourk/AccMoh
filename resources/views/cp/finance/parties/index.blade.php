@extends('cp.layout')
@section('title', $party['plural'] === 'الأشخاص' ? 'الأشخاص' : ($party['kind'] === 'worker' ? 'الموظفون' : 'الموردون'))
@section('content')
@php
    $heading = ['worker' => 'الموظفون', 'supplier' => 'الموردون', 'person' => 'الأشخاص'][$party['kind']];
    $isWorker = $party['kind'] === 'worker';
@endphp
<div class="cl-page" id="clPage">
    <div class="cl-top">
        <h1 class="cl-h">{{ $heading }}</h1>
        <div class="cl-sp"></div>
        <button type="button" class="cp-btn cp-btn-primary" id="clNew"><span class="material-symbols-outlined">add</span> {{ $party['noun'] }} جديد</button>
    </div>

    <div class="cl-tools">
        <label class="cl-search" for="clQ">
            <span class="material-symbols-outlined">search</span>
            <input id="clQ" type="search" placeholder="ابحث بالاسم أو الهاتف" autocomplete="off">
        </label>
        <div class="cl-seg" role="tablist" aria-label="عرض">
            <button type="button" class="on" data-f="all" role="tab">الكل</button>
            <button type="button" data-f="due" role="tab">عليه رصيد</button>
            <button type="button" data-f="credit" role="tab">إله رصيد</button>
            <button type="button" data-f="arch" role="tab">مؤرشف</button>
        </div>
        <div id="clSort"></div>
    </div>

    <div class="cl-summary" id="clSummary"></div>

    <div class="cl-list" id="clList"></div>
</div>

<div class="cl-sheet" id="clSheet" hidden>
    <form class="cl-dialog" id="clForm" method="post" action="{{ $storeUrl }}" role="dialog" aria-modal="true" aria-labelledby="clSheetTitle" novalidate>
        @csrf
        <input type="hidden" name="_method" id="clMethod" value="POST">
        <input type="hidden" name="return" value="list">
        <h2 id="clSheetTitle"></h2>
        <div class="cl-field">
            <label for="fName">الاسم</label>
            <input id="fName" name="name" maxlength="255">
        </div>
        <div class="cl-field">
            <label for="fDetail">{{ $party['detail'] }}</label>
            <input id="fDetail" name="detail" maxlength="255" placeholder="اختياري">
        </div>
        <div class="cl-field">
            <label for="fPhone">الهاتف</label>
            <input id="fPhone" name="phone" inputmode="numeric" dir="ltr" placeholder="اختياري · 05xxxxxxxx">
            <small class="cl-err" id="phoneErr" hidden>الرقم لازم يكون 10 خانات ويبدأ بـ 05</small>
        </div>
        @if($isWorker)
            <div class="cs-two">
                <div class="cl-field">
                    <label for="fSalary">الراتب الشهري</label>
                    <input id="fSalary" name="salary_amount" inputmode="decimal" dir="ltr" placeholder="اختياري">
                </div>
                <div class="cl-field">
                    <label for="fSalaryFrom">من تاريخ</label>
                    <input id="fSalaryFrom" name="salary_from" inputmode="numeric" dir="ltr" placeholder="يوم/شهر/سنة">
                </div>
            </div>
            <div class="cl-field">
                <label for="fSalaryTo">إيقاف الراتب من</label>
                <input id="fSalaryTo" name="salary_to" inputmode="numeric" dir="ltr" placeholder="اختياري · يوم/شهر/سنة">
                <small class="cs-hint">الراتب ما بينسجّل لحاله. بس بيعبّي نافذة «مستحق» بالمبلغ.</small>
            </div>
        @endif
        <div class="cl-field">
            <label for="fNotes">ملاحظات</label>
            <textarea id="fNotes" name="notes" rows="2" placeholder="اختياري"></textarea>
        </div>
        <p class="cl-dup" id="clDup" hidden><span class="material-symbols-outlined">info</span> في {{ $party['noun'] }} بنفس الاسم: <b id="clDupName"></b></p>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary" id="fSave" disabled>حفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" id="fCancel">إلغاء</button>
        </div>
    </form>
</div>

<form method="post" id="clArchForm" hidden>@csrf<input type="hidden" name="_method" id="clArchMethod" value="DELETE"></form>

<script type="application/json" id="clData">{!! json_encode(['rows' => $rows, 'flash' => $flash, 'storeUrl' => $storeUrl, 'party' => $party, 'today' => now()->toDateString(), 'symbols' => \App\Models\Currency::query()->pluck('symbol', 'code')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="{{ asset('assets/js/cp-list.js') }}?v={{ @filemtime(public_path('assets/js/cp-list.js')) }}"></script>
<script>
(function () {
    const D = JSON.parse(document.getElementById('clData').textContent);
    const P = D.party;
    const rows = D.rows;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const norm = (s) => String(s || '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').replace(/\s+/g, ' ').trim().toLowerCase();
    const fmt = (n) => Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dayLabel = (d) => { if (!d) return ''; const [y, m, dd] = d.split('-').map(Number); return dd + '/' + m + '/' + y; };
    let filter = 'all', editing = null;
    const sorter = cpList.sortMenu($('clSort'), P.kind, () => render());

    const due = (c) => c.balances.some((b) => b.amount > 0);
    const credit = (c) => c.balances.some((b) => b.amount < 0);
    function amountHtml(c) {
        if (!c.balances.length) return '<div class="cl-amt zero">مسدّد</div>';
        return '<div class="cl-amt-wrap">' + c.balances.map((b) =>
            `<div class="cl-amt ${b.amount > 0 ? 'due' : 'credit'}">${esc(b.label)}</div>`).join('') + '</div>';
    }
    function render() {
        const q = norm($('clQ').value);
        let list = rows.filter((c) => filter === 'arch' ? c.archived : !c.archived);
        if (filter === 'due') list = list.filter(due);
        if (filter === 'credit') list = list.filter(credit);
        if (q) list = list.filter((c) => norm([c.name, c.sub, c.phone, c.notes].join(' ')).includes(q));
        list = cpList.sort(list, sorter.get(), (c) => c.name, D.today);
        $('clList').innerHTML = list.length ? list.map((c) => `
            <div class="cl-row${c.id === D.flash ? ' is-new' : ''}" tabindex="0" data-id="${c.id}">
                <div class="cl-av">${esc((c.name.trim()[0]) || '؟')}</div>
                <div class="cl-who"><b>${c.pinned ? '<span class="cl-star">★</span> ' : ''}${esc(c.name)}</b><span>${esc(c.sub)}</span>${cpList.ageLine(c, D.today)}</div>
                ${amountHtml(c)}
                <button type="button" class="cl-more" aria-label="خيارات ${esc(c.name)}">⋯</button>
                <div class="cl-menu" hidden>
                    <a href="${esc(c.pdf)}" target="_blank" rel="noopener">تصدير كشف حساب</a>
                    <button type="button" data-act="edit">تعديل</button>
                    <button type="button" data-act="pin">${c.pinned ? 'إلغاء التثبيت' : '★ تثبيت'}</button>
                    <hr>
                    ${c.archived ? '<button type="button" data-act="restore">استرجاع من الأرشيف</button>' : '<button type="button" class="danger" data-act="archive">أرشفة</button>'}
                </div>
            </div>`).join('')
            : `<p class="cl-empty">${filter === 'arch' ? 'ما في حدا مؤرشف.' : (q ? 'ما في حدا بهالاسم.' : 'ما في حدا هون.')}</p>`;
        $('clSummary').innerHTML = cpList.summary(list, P.noun, D.symbols);
        if (D.flash) { const el = document.querySelector('.cl-row.is-new'); if (el) el.scrollIntoView({ block: 'center' }); D.flash = 0; }
    }
    function closeMenus() { document.querySelectorAll('.cl-menu').forEach((m) => { m.hidden = true; }); }
    $('clList').addEventListener('click', (e) => {
        const row = e.target.closest('.cl-row'); if (!row) return;
        const c = rows.find((x) => x.id === +row.dataset.id);
        const more = e.target.closest('.cl-more');
        if (more) { e.stopPropagation(); const m = more.nextElementSibling; const open = m.hidden; closeMenus(); m.hidden = !open; return; }
        const act = e.target.closest('[data-act]');
        if (act) { closeMenus(); action(act.dataset.act, c); return; }
        if (e.target.closest('.cl-menu')) return;
        window.location.href = c.show;
    });
    $('clList').addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.classList.contains('cl-row')) window.location.href = rows.find((x) => x.id === +e.target.dataset.id).show; });
    document.addEventListener('click', (e) => { if (!e.target.closest('.cl-menu')) closeMenus(); });
    function submitArch(url, method) { const f = $('clArchForm'); f.action = url; $('clArchMethod').value = method; f.submit(); }
    function action(act, c) {
        if (act === 'edit') return openSheet(c);
        if (act === 'restore') return submitArch(c.restore, 'POST');
        if (act === 'pin') return cpList.pin(c).then(render).catch(() => window.alert('ما زبط التثبيت، جرّب كمان مرة.'));
        const bal = c.balances.length ? ' رصيده مش صفر (' + c.balances.map((b) => (b.amount > 0 ? 'عليه ' : 'إله ') + b.label).join('، ') + ')، وبيضل محفوظ مع حركاته.' : '';
        const msg = 'أرشفة «' + c.name + '»؟' + bal + ' بتلاقيه بتاب «مؤرشف» وبتقدر ترجّعه.';
        const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
        ask.then((ok) => { if (ok) submitArch(c.archive, 'DELETE'); });
    }
    document.querySelectorAll('.cl-seg button').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('.cl-seg button').forEach((x) => x.classList.toggle('on', x === b));
        filter = b.dataset.f; render();
    }));
    $('clQ').addEventListener('input', render);

    const fName = $('fName'), fPhone = $('fPhone'), save = $('fSave');
    function openSheet(c) {
        editing = c || null;
        $('clSheetTitle').textContent = c ? 'تعديل ' + c.name : P.noun + ' جديد';
        save.textContent = c ? 'حفظ التعديل' : 'حفظ';
        $('clForm').action = c ? c.update : D.storeUrl;
        $('clMethod').value = c ? 'PUT' : 'POST';
        fName.value = c ? c.name : '';
        $('fDetail').value = c ? c.fields.detail : '';
        fPhone.value = c ? c.phone : '';
        $('fNotes').value = c ? c.notes : '';
        if ($('fSalary')) {
            $('fSalary').value = c && c.fields.salary_amount ? c.fields.salary_amount : '';
            $('fSalaryFrom').value = c ? dayLabel(c.fields.salary_from) : '';
            $('fSalaryTo').value = c ? dayLabel(c.fields.salary_to) : '';
        }
        $('clSheet').hidden = false;
        check();
        setTimeout(() => fName.focus(), 30);
    }
    function closeSheet() { $('clSheet').hidden = true; }
    function check() {
        const p = fPhone.value.trim();
        const okPhone = !p || /^05\d{8}$/.test(p);
        $('phoneErr').hidden = okPhone || p.length < 2;
        const n = norm(fName.value);
        const dup = n.length >= 3 ? rows.find((r) => (!editing || r.id !== editing.id) && norm(r.name) === n) : null;
        $('clDup').hidden = !dup;
        if (dup) $('clDupName').textContent = dup.name + (dup.archived ? ' (مؤرشف)' : '');
        save.disabled = !fName.value.trim() || !okPhone;
    }
    [fName, fPhone].forEach((el) => el.addEventListener('input', check));
    $('clNew').addEventListener('click', () => openSheet(null));
    $('fCancel').addEventListener('click', closeSheet);
    $('clSheet').addEventListener('click', (e) => { if (e.target.id === 'clSheet') closeSheet(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('clSheet').hidden) closeSheet(); });
    $('clForm').addEventListener('submit', (e) => { if (save.disabled) e.preventDefault(); });
    @if($errors->any() && old('return') === 'list')
        openSheet(null);
        fName.value = @json(old('name', ''));
        $('fDetail').value = @json(old('detail', ''));
        fPhone.value = @json(old('phone', ''));
        check();
    @endif

    /* ——— ضل مكانك بعد الحفظ أو الحذف ——— */
    const kept = window.cpKeep && cpKeep.take();
    if (kept) {
        filter = kept.filter || filter; $('clQ').value = kept.q || '';
        document.querySelectorAll('.cl-seg button').forEach((x) => x.classList.toggle('on', x.dataset.f === filter));
    }
    if (window.cpKeep) cpKeep.track(() => ({ filter, q: $('clQ').value }));
    render();
})();
</script>
@endsection
