@extends('cp.layout')
@section('title', 'الزبائن')
@section('content')
<div class="cl-page" id="clPage">
    @include('cp.partials.subs-banner')
    <div class="cl-top">
        <h1 class="cl-h">الزبائن</h1>
        <div class="cl-sp"></div>
        <a href="{{ route('cp.clients.import') }}" class="cl-ghost" title="استيراد من إكسيل"><span class="material-symbols-outlined">upload_file</span></a>
        <button type="button" class="cp-btn cp-btn-primary" id="clNew"><span class="material-symbols-outlined">add</span> زبون جديد</button>
    </div>

    <div class="cl-tools">
        <label class="cl-search" for="clQ">
            <span class="material-symbols-outlined">search</span>
            <input id="clQ" type="search" placeholder="ابحث بالجهة أو الاسم أو الهاتف" autocomplete="off">
        </label>
        <div class="cl-seg" role="tablist" aria-label="عرض">
            <button type="button" class="on" data-f="all" role="tab">الكل</button>
            <button type="button" data-f="due" role="tab">عليهم رصيد</button>
            <button type="button" data-f="credit" role="tab">إلهم رصيد</button>
            <button type="button" data-f="arch" role="tab">مؤرشف</button>
        </div>
    </div>

    <div class="cl-summary"><b id="clCount">0</b> <span id="clCountWord">زبون</span> · المستحق عليهم <b id="clTotal">0</b></div>
    <div class="cl-legend"><span><i class="due"></i>عليه للشركة</span><span><i class="credit"></i>إله رصيد عندنا</span><span><i class="zero"></i>مسدّد</span></div>

    <div class="cl-list" id="clList"></div>
</div>

{{-- نافذة إضافة وتعديل الزبون --}}
<div class="cl-sheet" id="clSheet" hidden>
    <form class="cl-dialog" id="clForm" method="post" action="{{ route('cp.clients.store') }}" role="dialog" aria-modal="true" aria-labelledby="clSheetTitle" novalidate>
        @csrf
        <input type="hidden" name="_method" id="clMethod" value="POST">
        <input type="hidden" name="return" value="list">
        <h2 id="clSheetTitle">زبون جديد</h2>
        <div class="cl-field">
            <label for="fOrg">الجهة</label>
            <input id="fOrg" name="company_name" maxlength="255" placeholder="اختياري، وإذا فاضية الاسم بيحل محلها">
        </div>
        <div class="cl-field">
            <label for="fName">الاسم</label>
            <input id="fName" name="name" maxlength="255">
        </div>
        <div class="cl-field">
            <label for="fPhone">الهاتف</label>
            <input id="fPhone" name="phone" inputmode="numeric" dir="ltr" placeholder="اختياري · 05xxxxxxxx">
            <small class="cl-err" id="phoneErr" hidden>الرقم لازم يكون 10 خانات ويبدأ بـ 05</small>
        </div>
        <div class="cl-field">
            <label for="fNotes">ملاحظات</label>
            <textarea id="fNotes" name="notes" rows="2" placeholder="اختياري"></textarea>
        </div>
        <p class="cl-dup" id="clDup" hidden><span class="material-symbols-outlined">info</span> في زبون مشابه: <b id="clDupName"></b></p>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary" id="fSave" disabled>حفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" id="fCancel">إلغاء</button>
        </div>
    </form>
</div>

{{-- نموذج الأرشفة والاسترجاع --}}
<form method="post" id="clArchForm" hidden>@csrf<input type="hidden" name="_method" id="clArchMethod" value="DELETE"></form>

<script type="application/json" id="clData">{!! json_encode(['clients' => $clients, 'flash' => $flash, 'storeUrl' => route('cp.clients.store')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script>
(function () {
    const D = JSON.parse(document.getElementById('clData').textContent);
    const clients = D.clients;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const norm = (s) => String(s || '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').replace(/\s+/g, ' ').trim().toLowerCase();
    const fmt = (n) => Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let filter = 'all';
    let editing = null;

    const due = (c) => c.balances.some((b) => b.amount > 0);
    const credit = (c) => c.balances.some((b) => b.amount < 0);
    const title = (c) => c.org || c.name;

    function amountHtml(c) {
        if (!c.balances.length) return '<div class="cl-amt zero">مسدّد</div>';
        return '<div class="cl-amt-wrap">' + c.balances.map((b) =>
            `<div class="cl-amt ${b.amount > 0 ? 'due' : 'credit'}" title="${b.amount > 0 ? 'عليه' : 'إله'}">${esc(b.label)}</div>`
        ).join('') + '</div>';
    }

    function render() {
        const q = norm($('clQ').value);
        let rows = clients.filter((c) => filter === 'arch' ? c.archived : !c.archived);
        if (filter === 'due') rows = rows.filter(due);
        if (filter === 'credit') rows = rows.filter(credit);
        if (q) rows = rows.filter((c) => norm([c.org, c.name, c.phone, c.notes].join(' ')).includes(q));

        $('clList').innerHTML = rows.length ? rows.map((c) => `
            <div class="cl-row${c.id === D.flash ? ' is-new' : ''}" tabindex="0" data-id="${c.id}">
                <div class="cl-av">${esc((title(c).trim()[0]) || '؟')}</div>
                <div class="cl-who"><b>${esc(title(c))}</b><span>${esc(c.org ? c.name : '')}</span></div>
                ${amountHtml(c)}
                <button type="button" class="cl-more" aria-label="خيارات ${esc(title(c))}">⋯</button>
                <div class="cl-menu" hidden>
                    <a href="${esc(c.pdf)}" target="_blank" rel="noopener">تصدير كشف حساب</a>
                    <button type="button" data-act="edit">تعديل</button>
                    <hr>
                    ${c.archived ? '<button type="button" data-act="restore">استرجاع من الأرشيف</button>' : '<button type="button" class="danger" data-act="archive">أرشفة</button>'}
                </div>
            </div>`).join('')
            : `<p class="cl-empty">${filter === 'arch' ? 'ما في زبائن مؤرشفين.' : (q ? 'ما في زبون بهالاسم.' : 'ما في زبائن هون.')}</p>`;

        $('clCount').textContent = rows.length;
        const totals = {};
        rows.forEach((c) => c.balances.forEach((b) => { if (b.amount > 0) totals[b.code] = (totals[b.code] || 0) + b.amount; }));
        const parts = Object.entries(totals).map(([code, v]) => fmt(v) + ' ' + ((D.symbols || {})[code] || code));
        $('clTotal').textContent = parts.length ? parts.join(' · ') : '0.00';

        if (D.flash) {
            const el = document.querySelector('.cl-row.is-new');
            if (el) el.scrollIntoView({ block: 'center' });
            D.flash = 0;
        }
    }

    function closeMenus() { document.querySelectorAll('.cl-menu').forEach((m) => { m.hidden = true; }); }

    $('clList').addEventListener('click', (e) => {
        const more = e.target.closest('.cl-more');
        const row = e.target.closest('.cl-row');
        if (!row) return;
        const c = clients.find((x) => x.id === +row.dataset.id);
        if (more) {
            e.stopPropagation();
            const menu = more.nextElementSibling;
            const open = menu.hidden;
            closeMenus();
            menu.hidden = !open;
            return;
        }
        const act = e.target.closest('[data-act]');
        if (act) { closeMenus(); action(act.dataset.act, c); return; }
        if (e.target.closest('.cl-menu')) return;
        window.location.href = c.show;
    });
    $('clList').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.classList.contains('cl-row')) {
            window.location.href = clients.find((x) => x.id === +e.target.dataset.id).show;
        }
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.cl-menu')) closeMenus(); });

    function submitArch(url, method) {
        const f = $('clArchForm');
        f.action = url;
        $('clArchMethod').value = method;
        f.submit();
    }

    function action(act, c) {
        if (act === 'edit') return openSheet(c);
        if (act === 'restore') return submitArch(c.restore, 'POST');
        if (act === 'archive') {
            const bal = c.balances.length ? ' رصيده مش صفر (' + c.balances.map((b) => (b.amount > 0 ? 'عليه ' : 'إله ') + b.label).join('، ') + ')، وبيضل محفوظ مع كل حركاته.' : '';
            const msg = 'أرشفة «' + title(c) + '»؟' + bal + ' بتلاقيه بتاب «مؤرشف» وبتقدر ترجّعه.';
            const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
            ask.then((ok) => { if (ok) submitArch(c.archive, 'DELETE'); });
        }
    }

    document.querySelectorAll('.cl-seg button').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('.cl-seg button').forEach((x) => x.classList.toggle('on', x === b));
        filter = b.dataset.f;
        render();
    }));
    $('clQ').addEventListener('input', render);

    /* نافذة الإضافة والتعديل */
    const fOrg = $('fOrg'), fName = $('fName'), fPhone = $('fPhone'), fNotes = $('fNotes'), save = $('fSave');
    function openSheet(c) {
        editing = c || null;
        $('clSheetTitle').textContent = c ? 'تعديل الزبون' : 'زبون جديد';
        save.textContent = c ? 'حفظ التعديل' : 'حفظ';
        $('clForm').action = c ? c.update : D.storeUrl;
        $('clMethod').value = c ? 'PUT' : 'POST';
        fOrg.value = c ? c.org : '';
        fName.value = c ? c.name : '';
        fPhone.value = c ? c.phone : '';
        fNotes.value = c ? c.notes : '';
        $('clSheet').hidden = false;
        check();
        setTimeout(() => (c ? fName : fOrg).focus(), 30);
    }
    function closeSheet() { $('clSheet').hidden = true; }
    function check() {
        const p = fPhone.value.trim();
        const okPhone = !p || /^05\d{8}$/.test(p);
        $('phoneErr').hidden = okPhone || p.length < 2;
        const o = norm(fOrg.value), n = norm(fName.value);
        let dup = null;
        if (o.length >= 3 || n.length >= 3) {
            dup = clients.find((r) => (!editing || r.id !== editing.id) &&
                ((o.length >= 3 && norm(r.org).includes(o)) || (n.length >= 3 && norm(r.name).includes(n))));
        }
        $('clDup').hidden = !dup;
        if (dup) $('clDupName').textContent = (dup.org ? dup.org + '، ' : '') + dup.name + (dup.archived ? ' (مؤرشف)' : '');
        save.disabled = !fName.value.trim() || !okPhone;
    }
    [fOrg, fName, fPhone].forEach((el) => el.addEventListener('input', check));
    fNotes.addEventListener('input', () => { fNotes.style.height = ''; fNotes.style.height = fNotes.scrollHeight + 'px'; });
    $('clNew').addEventListener('click', () => openSheet(null));
    $('fCancel').addEventListener('click', closeSheet);
    $('clSheet').addEventListener('click', (e) => { if (e.target.id === 'clSheet') closeSheet(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('clSheet').hidden) closeSheet(); });
    $('clForm').addEventListener('submit', (e) => { if (save.disabled) e.preventDefault(); });

    @if($errors->any() && old('return') === 'list')
        openSheet(null);
        fOrg.value = @json(old('company_name', ''));
        fName.value = @json(old('name', ''));
        fPhone.value = @json(old('phone', ''));
        fNotes.value = @json(old('notes', ''));
        check();
    @endif

    D.symbols = @json(collect($currencyFormats)->map(fn ($c) => $c['symbol'])->all());
    render();
})();
</script>
@endsection
