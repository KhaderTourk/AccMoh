@extends('cp.layout')
@section('title', 'الموظفين')
@section('body_class', 'bs')
@section('content')
{{-- شاشة الموظفين على قالب الزبائن (15/11/2026، المراحل 1–2 معتمدة): كرتين فوق، بحث بالاقتراحات، تابات، وجدول بيترتّب من راسه.
     الاتجاه مقلوب: الرصيد الموجب = إله (أخضر)، السالب = عليه سلفة (وردي). --}}
<div class="cl-page cl2" id="clPage">
    <div class="cl-top">
        <h1 class="cl-h">الموظفين</h1>
        <div class="cl-sp"></div>
        <button type="button" class="bs-btn" id="clNew">＋ موظف جديد</button>
    </div>

    <div class="cl2-cards" id="clCards"></div>

    <div class="cl-tools cl2-tools">
        <div class="cl2-search">
            <label class="cl-search" for="clQ">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="clQ" type="search" placeholder="ابحث بالاسم أو المسمّى أو الهاتف" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="clSug" aria-autocomplete="list">
            </label>
            <div class="cl2-sug" id="clSug" role="listbox" hidden></div>
        </div>
        <div class="cl-seg cl2-seg" id="clTabs" role="tablist" aria-label="عرض"></div>
    </div>

    <div class="cl2-table">
        <table data-sortable="off">
            <thead><tr id="clHead"></tr></thead>
            <tbody id="clBody"></tbody>
        </table>
    </div>
    <p class="cl2-hint" id="clHint"></p>
</div>

{{-- نافذة إضافة وتعديل الموظف، و«راتب شهري» من ⋯ --}}
<div class="cl-sheet" id="clSheet" hidden>
    <form class="cl-dialog" id="clForm" method="post" action="{{ route('cp.workers.store') }}" role="dialog" aria-modal="true" aria-labelledby="clSheetTitle" novalidate>
        @csrf
        <input type="hidden" name="_method" id="clMethod" value="POST">
        <input type="hidden" name="return" id="clReturn" value="list">
        <input type="hidden" name="stay" id="clStay" value="0">
        <input type="hidden" name="panel" id="clPanel" value="edit">
        <h2 id="clSheetTitle">موظف جديد</h2>
        <div class="cl-field">
            <label for="fName">الاسم</label>
            <input id="fName" name="name" maxlength="255">
        </div>
        <div class="cl-field">
            <label for="fOrg">المسمّى الوظيفي</label>
            <input id="fOrg" name="job_title" maxlength="255" placeholder="اختياري · مثلاً: مصمم جرافيك">
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
        <div class="cl2-subs" id="clSubs" hidden></div>
        <p class="cl-dup" id="clDup" hidden><span class="material-symbols-outlined">info</span> في موظف مشابه: <b id="clDupName"></b></p>
        <div class="cl-dup cl-twin" id="clTwin" hidden>
            <p><span class="material-symbols-outlined">inventory_2</span> «<b id="clTwinName"></b>» موجود بالأرشيف.</p>
            <div class="cl-twin-act">
                <button type="button" class="cp-btn cp-btn-primary" id="clTwinRestore">استرجعه</button>
                <button type="button" class="cp-btn cp-btn-ghost" id="clTwinNew">ضيفه جديد</button>
            </div>
        </div>
        <div class="cl-actions">
            <span class="cl2-saved" id="clSaved"></span>
            <button type="submit" class="cp-btn cp-btn-primary" id="fSave" disabled>احفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" id="fCancel">إلغاء</button>
        </div>
    </form>
</div>

{{-- نموذج الأرشفة والاسترجاع --}}
<form method="post" id="clArchForm" hidden>@csrf<input type="hidden" name="_method" id="clArchMethod" value="DELETE"></form>

<script type="application/json" id="clData">{!! json_encode([
    'clients' => $workers,
    'head' => $head,
    'types' => $types,
    'lastLabel' => $lastLabel,
    'flash' => $flash,
    'edit' => (int) request('edit'),
    'panel' => request('panel') === 'subs' ? 'subs' : 'edit',
    'back' => request('back') === 'show' ? 'show' : 'list',
    'saved' => session('success') && request('edit') ? session('success') : '',
    'showArchived' => \App\Support\ArchiveVisibility::showing(),
    'storeUrl' => route('cp.workers.store'),
    'today' => now()->toDateString(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="{{ asset('assets/js/cp-list.js') }}?v={{ @filemtime(public_path('assets/js/cp-list.js')) }}"></script>
<script>
(function () {
    const D = JSON.parse(document.getElementById('clData').textContent);
    const clients = D.clients;
    const H = D.head;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const norm = (s) => String(s || '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').replace(/\s+/g, ' ').trim().toLowerCase();
    const num = (n) => Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const N = (t) => `<bdi class="num" dir="ltr">${t}</bdi>`;
    const title = (c) => c.name;
    const subLine = (c) => c.job || c.phone || '';
    // الرصيد لحد 10 شيكل بالاتجاهين بينعرض «مسدّد» (قرار 9/11/2026). عرض بس.
    const SMALL = 10;

    /* ——— الكروت (المرحلة 2): «علي» بالأخضر و«هالشهر»، حسب تاريخ الحركة ——— */
    function pctLine(p) { if (p === null || p === undefined) return ''; const up = p >= 0; return `<i class="${up ? 'up' : 'down'}">${up ? '▲' : '▼'} ${Math.abs(p)}%</i> عن الشهر الماضي`; }
    function renderCards() {
        const ch = H.change, chUp = ch >= 0;
        const link = (name, url) => (url ? `<a class="cl2-hlink" href="${esc(url)}">${esc(name)}</a>` : esc(name));
        const who = (n, name, url) => (n === 1 && name ? link(name, url) : n + ' ' + (n === 1 ? 'موظف' : 'موظفين'));
        $('clCards').innerHTML = `
        <article class="cl2-kc"><header><b>علي</b><span>موظفين إلهم رصيد</span></header>
            <div class="cl2-big credit">${N(num(H.owed) + ' ₪')}</div><div class="cl2-cmp">لـ ${H.owedCount} ${H.owedCount === 1 ? 'موظف' : 'موظفين'}</div>
            <div class="cl2-rows">
                <div><span>آخر دفعة أكثر من 30 يوم</span><span><b>${N(num(H.late))}</b><em>${who(H.lateCount, H.lateName, H.lateShow)}</em></span></div>
                <div><span>راتب ${N(D.lastLabel)} لسا ما انضاف</span><span><b>${N(num(H.miss))}</b><em>${who(H.missCount, H.missName, H.missShow)}</em></span></div>
                <div><span>أكبر رصيد</span><span>${H.top ? `<b>${N(num(H.top.amount))}</b><em>${link(H.top.name, H.top.show)}</em>` : '<b>—</b>'}</span></div>
            </div></article>
        <article class="cl2-kc"><header><b>هالشهر</b><span>${N(H.month)}</span></header>
            <div class="cl2-big">${N(num(H.dues) + ' ₪')}</div><div class="cl2-cmp">مستحقات انضافت${H.duesPct !== null ? ' · ' + pctLine(H.duesPct) : ''}</div>
            <div class="cl2-rows">
                <div><span>دفعات طلعت</span><span><b>${N(num(H.paid))}</b>${H.paidPct !== null ? `<em>${pctLine(H.paidPct)}</em>` : ''}</span></div>
                <div><span>نسبة الدفع</span><span><b>${H.ratio === null ? '—' : N(H.ratio + '%')}</b><em>من المستحقات</em></span></div>
                <div><span>${chUp ? 'زاد اللي علي هالشهر' : 'نقص اللي علي هالشهر'}</span><span><b>${N(num(ch))}</b><em>أول الشهر كان ${N(num(H.owedStart))}</em></span></div>
            </div></article>`;
    }

    /* ——— التابات والجدول (قرار 15) ——— */
    function reasons(c) {
        const out = [];
        if (c.archived) return out;
        if (c.ils > SMALL && c.payDays !== null && c.payDays > 30) out.push('آخر دفعة قبل ' + c.payDays + ' يوم');
        if (c.ils > 2000) out.push('إله أكثر من 2,000 ₪');
        if (c.missLast) out.push('راتب ' + D.lastLabel + ' ناقص');
        return out;
    }
    const salary = (c) => c.subs.reduce((a, s) => a + s.amount, 0);
    const TABS = [
        ['all', 'الكل', (c) => !c.archived],
        ['due', 'إله رصيد', (c) => !c.archived && c.ils > SMALL],
        ['owe', 'عليه رصيد', (c) => !c.archived && c.ils < -SMALL],
        ['set', 'مسدّد', (c) => !c.archived && Math.abs(c.ils) <= SMALL],
        // «متابعة» (15/11/2026): إله رصيد وآخر دفعة إله أكثر من 30 يوم، أو إله أكثر من 2000 ₪، أو راتب الشهر الماضي لسا ما انضاف.
        ['need', 'متابعة', (c) => reasons(c).length > 0],
    ];
    if (D.showArchived) TABS.push(['arch', 'مؤرشف', (c) => c.archived]);
    const COLS = [
        { k: 'n', t: 'الاسم', v: (c) => norm(title(c)) },
        { k: 'pay', t: 'آخر دفعة', v: (c) => (c.payDays === null ? -1 : c.payDays) },
        { k: 'ms', t: 'راتب شهري', v: (c) => salary(c) },
        { k: 'bal', t: 'الرصيد', v: (c) => c.ils },
    ];
    const LBL = { n: ['من أ لـ ي', 'من ي لـ أ'], pay: ['الأقدم دفعة أول', 'الأحدث دفعة أول'], ms: ['الراتب الأعلى أول', 'الراتب الأقل أول'], bal: ['اللي إله الأكثر أول', 'اللي عليه الأكثر أول'] };
    // الفلتر الأساسي «الكل»، والترتيب بيضل على آخر واحد استعملته (م. محمد 15/11).
    const SORT_KEY = 'cp.workers.sort';
    let tab = 'all', sort = { k: 'bal', dir: 0 };
    try { const v = JSON.parse(localStorage.getItem(SORT_KEY) || 'null'); if (v && COLS.some((c) => c.k === v.k) && (v.dir === 0 || v.dir === 1)) sort = v; } catch (e) { /* بدون تخزين */ }
    function keepSort() { try { localStorage.setItem(SORT_KEY, JSON.stringify(sort)); } catch (e) { /* بدون تخزين */ } }
    /* القوائم المنبثقة ثابتة على الشاشة، فما بتنقص لو الجدول قصير، وبتقلب لفوق لو ما في مكان تحت. */
    function place(menu, btn, side) {
        menu.style.position = 'fixed'; menu.style.right = 'auto'; menu.style.bottom = 'auto'; menu.hidden = false;
        const r = btn.getBoundingClientRect(), w = menu.offsetWidth, h = menu.offsetHeight;
        const vw = document.documentElement.clientWidth, vh = window.innerHeight;
        let top = r.bottom + 6;
        if (top + h > vh - 8) top = r.top - h - 6 >= 8 ? r.top - h - 6 : Math.max(8, vh - 8 - h);
        let left = side === 'right' ? r.right - w : r.left;
        left = Math.min(Math.max(8, left), vw - w - 8);
        menu.style.top = top + 'px'; menu.style.left = left + 'px';
    }

    function list() {
        const q = norm($('clQ').value);
        // البحث بيدوّر بكل الزباين، مش بس بالتاب المختار.
        let rows = q ? clients.filter((c) => (!c.archived || D.showArchived) && norm([c.name, c.job, c.phone, c.notes].join(' ')).includes(q))
            : clients.filter(TABS.find((t) => t[0] === tab)[2]);
        const col = COLS.find((x) => x.k === sort.k);
        const asc = (sort.k === 'n') !== (sort.dir === 1);
        rows = rows.slice().sort((a, b) => { const x = col.v(a), y = col.v(b); const r = typeof x === 'string' ? x.localeCompare(y, 'ar') : x - y; return asc ? r : -r; });
        return rows;
    }
    function payCell(c) {
        if (c.payDays === null) return '<span class="cl2-dash">—</span>';
        const t = c.payDays === 0 ? 'اليوم' : c.payDays === 1 ? 'مبارح' : `قبل ${c.payDays} يوم`;
        const why = tab === 'need' && !$('clQ').value.trim() ? reasons(c) : [];
        return `<span class="cl2-age${c.payDays > 30 ? ' late' : ''}">${t}</span>${why.length ? `<small class="cl2-why">${esc(why.join(' · '))}</small>` : ''}`;
    }
    function balCell(c) {
        const v = c.ils, cls = v > SMALL ? 'credit' : v < -SMALL ? 'due' : 'zero';
        return `<span class="cl2-amt ${cls}">${N(num(v))}</span>`;
    }
    function render() {
        const q = $('clQ').value.trim();
        $('clTabs').innerHTML = TABS.map((t) => `<button type="button" role="tab" aria-selected="${!q && tab === t[0]}" class="${!q && tab === t[0] ? 'on' : ''}${q ? ' dim' : ''}" data-t="${t[0]}">${t[1]}<em>${clients.filter(t[2]).length}</em></button>`).join('');
        $('clHead').innerHTML = COLS.map((c) => `<th class="${sort.k === c.k ? 'on' : ''}" aria-sort="${sort.k === c.k ? (((sort.k === 'n') !== (sort.dir === 1)) ? 'ascending' : 'descending') : 'none'}"><button type="button" data-k="${c.k}">${c.t}<span class="arr" aria-hidden="true">${sort.k === c.k ? (((sort.k === 'n') !== (sort.dir === 1)) ? '▲' : '▼') : '▼'}</span></button></th>`).join('') + '<th><span class="sr-only">خيارات</span></th>';
        const rows = list();
        $('clBody').innerHTML = rows.length ? rows.map((c) => `<tr data-id="${c.id}" tabindex="0" class="${c.id === D.flash ? 'is-new' : ''}${c.archived ? ' is-arch' : ''}">
            <td><div class="cl2-who"><span class="cl2-av">${esc((title(c).trim()[0]) || '؟')}</span><div><b>${esc(title(c))}${c.archived ? ' <em class="cs-arch">مؤرشف</em>' : ''}</b><small>${esc(subLine(c))}</small></div></div></td>
            <td>${payCell(c)}</td>
            <td>${c.subs.length ? `<span class="cl2-sal">${N(num(salary(c)))}${c.subs.length > 1 ? `<small>${c.subs.length} بنود</small>` : ''}</span>` : '<span class="cl2-dash">لا</span>'}</td>
            <td>${balCell(c)}</td>
            <td class="cl2-m"><button type="button" class="cl-more" aria-label="خيارات ${esc(title(c))}">⋯</button>
                <div class="cl-menu" hidden>
                    <a href="${esc(c.pdf)}" target="_blank" rel="noopener">كشف حساب</a>
                    <button type="button" data-act="edit">تعديل</button>
                    <button type="button" data-act="subs">راتب شهري</button>
                    <hr>
                    ${c.archived ? '<button type="button" data-act="restore">استرجاع من الأرشيف</button>' : '<button type="button" class="danger" data-act="archive">أرشفة</button>'}
                </div></td></tr>`).join('')
            : `<tr><td colspan="5" class="cl-empty">${q ? 'ما لقينا موظف بهالاسم.' : 'ما في موظفين هون.'}</td></tr>`;
        $('clHint').textContent = (q ? 'البحث بيدوّر بكل التابات · ' : '') + `مرتّب حسب ${COLS.find((x) => x.k === sort.k).t}: ${LBL[sort.k][sort.dir]}`;
        if (D.flash) { const el = document.querySelector('#clBody tr.is-new'); if (el) el.scrollIntoView({ block: 'center' }); D.flash = 0; }
    }
    $('clTabs').addEventListener('click', (e) => { const b = e.target.closest('[data-t]'); if (!b) return; tab = b.dataset.t; $('clQ').value = ''; hideSug(); render(); });
    $('clHead').addEventListener('click', (e) => { const b = e.target.closest('[data-k]'); if (!b) return; const k = b.dataset.k; sort = sort.k === k ? { k, dir: 1 - sort.dir } : { k, dir: 0 }; keepSort(); render(); });

    function closeMenus() { document.querySelectorAll('.cl-menu').forEach((m) => { m.hidden = true; }); }
    $('clBody').addEventListener('click', (e) => {
        const row = e.target.closest('tr[data-id]'); if (!row) return;
        const c = clients.find((x) => x.id === +row.dataset.id);
        const more = e.target.closest('.cl-more');
        if (more) { e.stopPropagation(); const m = more.nextElementSibling; const open = m.hidden; closeMenus(); if (open) place(m, more, 'left'); return; }
        const act = e.target.closest('[data-act]');
        if (act) { closeMenus(); action(act.dataset.act, c); return; }
        if (e.target.closest('.cl-menu')) return;
        window.location.href = c.show;
    });
    $('clBody').addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.matches('tr[data-id]')) window.location.href = clients.find((x) => x.id === +e.target.dataset.id).show; });
    document.addEventListener('click', (e) => { if (!e.target.closest('.cl-menu')) closeMenus(); if (!e.target.closest('.cl2-search')) hideSug(); });
    document.addEventListener('scroll', (e) => { if (e.target.closest && e.target.closest('.cl-menu')) return; closeMenus(); if (addOpen) { addOpen = false; paintSubs(); } }, true);
    window.addEventListener('resize', closeMenus);

    function submitArch(url, method) { const f = $('clArchForm'); f.action = url; $('clArchMethod').value = method; f.submit(); }
    function action(act, c) {
        if (act === 'edit') return openSheet(c);
        if (act === 'subs') return openSheet(c, 'subs');
        if (act === 'restore') return submitArch(c.restore, 'POST');
        if (act === 'archive') {
            const bal = Math.abs(c.ils) >= 0.005 ? ` رصيده ${c.ils > 0 ? 'إله' : 'عليه'} ${num(c.ils)} ₪، وبيضل محفوظ مع كل حركاته.` : '';
            const msg = 'أرشفة «' + title(c) + '»؟' + bal + ' بيختفي من القوائم، وبتقدر تشوفه وترجّعه من الإعدادات ← العرض ← «إظهار المؤرشف».';
            const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
            ask.then((ok) => { if (ok) submitArch(c.archive, 'DELETE'); });
        }
    }

    /* ——— البحث بالاقتراحات (قرار 15) ——— */
    let sugI = -1, sugRows = [];
    function hideSug() { $('clSug').hidden = true; $('clQ').setAttribute('aria-expanded', 'false'); sugI = -1; }
    function paintSug() {
        const q = norm($('clQ').value);
        if (!q) { hideSug(); return; }
        const pool = clients.filter((c) => !c.archived || D.showArchived);
        const hit = (c) => norm([c.name, c.job, c.phone].join(' '));
        const starts = (c) => norm(c.name).startsWith(q);
        sugRows = pool.filter((c) => hit(c).includes(q)).sort((a, b) => (starts(b) - starts(a)) || title(a).localeCompare(title(b), 'ar')).slice(0, 7);
        $('clSug').innerHTML = sugRows.map((c, i) => `<button type="button" role="option" id="sug${i}" class="${i === sugI ? 'on' : ''}" data-s="${c.id}"><span><b>${esc(title(c))}</b><small>${esc(subLine(c))}</small></span>${balCell(c)}</button>`).join('')
            + `<button type="button" role="option" id="sug${sugRows.length}" class="cl2-sug-new${sugI === sugRows.length ? ' on' : ''}" data-s="new">＋ ضيفه موظف جديد «${esc($('clQ').value.trim())}»</button>`;
        $('clSug').hidden = false;
        $('clQ').setAttribute('aria-expanded', 'true');
        $('clQ').setAttribute('aria-activedescendant', sugI >= 0 ? 'sug' + sugI : '');
    }
    function pickSug(id) {
        if (id === 'new') { const v = $('clQ').value.trim(); hideSug(); openSheet(null); $('fName').value = v; check(); return; }
        const c = clients.find((x) => x.id === +id); if (c) window.location.href = c.show;
    }
    $('clQ').addEventListener('input', () => { sugI = -1; paintSug(); render(); });
    $('clQ').addEventListener('keydown', (e) => {
        if ($('clSug').hidden) return;
        const n = sugRows.length + 1;
        if (e.key === 'ArrowDown') { e.preventDefault(); sugI = (sugI + 1) % n; paintSug(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); sugI = (sugI - 1 + n) % n; paintSug(); }
        else if (e.key === 'Enter' && sugI >= 0) { e.preventDefault(); pickSug(sugI === sugRows.length ? 'new' : sugRows[sugI].id); }
        else if (e.key === 'Escape') { hideSug(); }
    });
    $('clSug').addEventListener('click', (e) => { const b = e.target.closest('[data-s]'); if (b) pickSug(b.dataset.s); });

    /* ——— نافذة الإضافة والتعديل + الاشتراك الشهري ——— */
    const fOrg = $('fOrg'), fName = $('fName'), fPhone = $('fPhone'), fNotes = $('fNotes'), save = $('fSave');
    let editing = null, mode = 'edit', allowNew = 0, subs = null, addOpen = false, offAsk = false;
    function typeName(id) { const t = D.types.find((x) => x.id === +id); return t ? t.name : 'راتب'; }
    function paintSubs() {
        const box = $('clSubs');
        // «راتب شهري» نافذة لحاله من ⋯، بنفس آلية اشتراك الزبون (المرحلة 1).
        box.hidden = !editing || mode !== 'subs';
        if (box.hidden) { box.innerHTML = ''; return; }
        const total = subs.rows.reduce((a, r) => a + (parseFloat(r.amount) || 0), 0);
        const used = new Set(subs.rows.map((r) => +r.type));
        const menu = D.types.filter((t) => !used.has(t.id)).map((t) => `<button type="button" data-add="${t.id}">${esc(t.name)}</button>`).join('');
        box.innerHTML = `<div class="cl2-sw"><span>إله راتب شهري</span><button type="button" class="cl2-switch${subs.on ? ' on' : ''}" id="clSw" role="switch" aria-checked="${subs.on}" aria-label="إله راتب شهري"></button></div>
            <input type="hidden" name="subs_sent" value="1"><input type="hidden" name="subs_on" value="${subs.on ? 1 : 0}">
            ${offAsk ? `<div class="cl2-off"><b>بدك تشيل كل بنوده الشهرية؟</b><p>الأشهر اللي انضافت قبل بتضل زي ما هي بالكشف.</p><div><button type="button" class="cp-btn cp-btn-primary" id="clOffOk">شيل الكل</button><button type="button" class="cp-btn cp-btn-ghost" id="clOffNo">إلغاء</button></div></div>` : ''}
            ${subs.on && !offAsk ? `<div class="cl2-svl">${subs.rows.map((r, i) => `<div class="cl2-sv"><label for="sa${i}">${esc(typeName(r.type))}</label>
                <input type="hidden" name="subs[${i}][type]" value="${r.type}">
                <span class="cp-amt"><input id="sa${i}" name="subs[${i}][amount]" data-i="${i}" inputmode="decimal" dir="ltr" placeholder="0.00" value="${esc(r.amount)}" aria-label="مبلغ ${esc(typeName(r.type))} الشهري"><span class="cl2-cur">₪</span></span>
                <button type="button" class="cl2-x" data-rm="${i}" aria-label="شيل ${esc(typeName(r.type))}">✕</button></div>`).join('')}
                <div class="cl2-addw"><button type="button" class="cl2-addb" id="clAddB">＋ أضف</button>
                ${addOpen ? `<div class="cl-menu cl2-amenu">${menu || '<span class="cl2-mg">كل الأنواع مضافة</span>'}<span class="cl2-mg">الأنواع من الإعدادات ← مستحقات الموظفين</span></div>` : ''}</div></div>
                ${subs.rows.length ? `<div class="cl2-tot"><span>المجموع الشهري</span>${N(num(total) + ' ₪')}</div>` : ''}` : ''}`;
    }
    function openSheet(c, m) {
        editing = c || null;
        mode = c && m === 'subs' ? 'subs' : 'edit';
        $('clForm').classList.toggle('is-subs', mode === 'subs');
        $('clPanel').value = mode;
        allowNew = 0; addOpen = false; offAsk = false;
        subs = { on: !!(c && c.subs.length), had: !!(c && c.subs.length), rows: c ? c.subs.map((s) => ({ type: s.type, amount: String(s.amount) })) : [] };
        $('clSheetTitle').textContent = mode === 'subs' ? 'راتب شهري · ' + title(c) : (c ? 'تعديل الموظف' : 'موظف جديد');
        $('clForm').action = c ? c.update : D.storeUrl;
        $('clMethod').value = c ? 'PUT' : 'POST';
        $('clReturn').value = c && D.back === 'show' ? 'show' : 'list';
        $('clStay').value = '0';
        fOrg.value = c ? c.job : ''; fName.value = c ? c.name : ''; fPhone.value = c ? c.phone : ''; fNotes.value = c ? c.notes : '';
        $('clSaved').textContent = '';
        paintSubs();
        $('clSheet').hidden = false;
        check();
        setTimeout(() => { if (mode === 'subs') { const f = $('clSubs').querySelector('input[data-i], #clAddB, #clSw'); if (f) f.focus(); } else fName.focus(); }, 30);
    }
    function closeSheet() { $('clSheet').hidden = true; if (D.back === 'show' && editing) window.location.href = editing.show; }
    $('clSubs').addEventListener('click', (e) => {
        const t = e.target;
        if (t.id === 'clSw') { e.stopPropagation(); addOpen = false; if (subs.on && subs.had) offAsk = true; else { subs.on = !subs.on; if (subs.on && !subs.rows.length) addOpen = true; } paintSubs(); check(); return; }
        if (t.id === 'clOffOk') { subs.on = false; subs.rows = []; offAsk = false; paintSubs(); check(); return; }
        if (t.id === 'clOffNo') { offAsk = false; paintSubs(); return; }
        if (t.id === 'clAddB') { e.stopPropagation(); addOpen = !addOpen; paintSubs(); return; }
        const a = t.closest('[data-add]');
        if (a) { subs.rows.push({ type: +a.dataset.add, amount: '' }); addOpen = false; paintSubs(); check(); const i = subs.rows.length - 1; setTimeout(() => $('sa' + i).focus(), 10); return; }
        const rm = t.closest('[data-rm]');
        if (rm) { subs.rows.splice(+rm.dataset.rm, 1); paintSubs(); check(); }
    });
    $('clSubs').addEventListener('input', (e) => { const i = e.target.dataset.i; if (i !== undefined) { subs.rows[+i].amount = e.target.value.replace(/[^0-9.]/g, ''); const tot = $('clSubs').querySelector('.cl2-tot bdi'); if (tot) tot.textContent = num(subs.rows.reduce((a, r) => a + (parseFloat(r.amount) || 0), 0)) + ' ₪'; check(); } });
    // Enter بخانة المبلغ بيحفظ وبيضل بنفس النافذة (قرار 15).
    $('clSubs').addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.dataset.i !== undefined) { e.preventDefault(); if (!save.disabled) { $('clStay').value = '1'; $('clForm').requestSubmit(); } } });
    document.addEventListener('click', (e) => { if (addOpen && !e.target.closest('.cl2-addw')) { addOpen = false; paintSubs(); } });
    // قائمة «أضف خدمة» بتنفتح تحت الزر، ولو ما في مكان بتقلب لفوق.
    new MutationObserver(() => { const m = $('clSubs').querySelector('.cl2-amenu'); if (m && !m.dataset.placed) { m.dataset.placed = '1'; place(m, $('clAddB'), 'right'); } }).observe($('clSubs'), { childList: true, subtree: true });

    function check() {
        const p = fPhone.value.trim();
        const okPhone = !p || /^05\d{8}$/.test(p);
        $('phoneErr').hidden = okPhone || p.length < 2;
        const n = norm(fName.value);
        let dup = null;
        if (n.length >= 3) {
            dup = clients.find((r) => (!editing || r.id !== editing.id) && norm(r.name).includes(n));
        }
        const twin = editing ? null : cpList.archivedTwin(clients, [n], p, (r) => [norm(r.name)]);
        const ask = twin && allowNew !== twin.id;
        $('clTwin').hidden = !ask;
        if (ask) { $('clTwinName').textContent = twin.name; $('clTwin').dataset.id = twin.id; }
        $('clDup').hidden = !dup || ask;
        if (dup) $('clDupName').textContent = dup.name + (dup.archived ? ' (مؤرشف)' : '');
        save.disabled = !fName.value.trim() || !okPhone || ask;
    }
    $('clTwinRestore').addEventListener('click', () => { const t = clients.find((x) => x.id === +$('clTwin').dataset.id); if (t) submitArch(t.restore, 'POST'); });
    $('clTwinNew').addEventListener('click', () => { allowNew = +$('clTwin').dataset.id; check(); });
    [fOrg, fName, fPhone].forEach((el) => el.addEventListener('input', check));
    fNotes.addEventListener('input', () => { fNotes.style.height = ''; fNotes.style.height = fNotes.scrollHeight + 'px'; });
    $('clNew').addEventListener('click', () => openSheet(null));
    $('fCancel').addEventListener('click', closeSheet);
    $('clSheet').addEventListener('click', (e) => { if (e.target.id === 'clSheet') closeSheet(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('clSheet').hidden) closeSheet(); });
    $('clForm').addEventListener('submit', (e) => { if (save.disabled) e.preventDefault(); });

    @if($errors->any() && old('return') === 'list')
        openSheet(null);
        fOrg.value = @json(old('job_title', ''));
        fName.value = @json(old('name', ''));
        fPhone.value = @json(old('phone', ''));
        fNotes.value = @json(old('notes', ''));
        check();
    @endif

    /* ——— ضل مكانك ——— */
    const kept = window.cpKeep && cpKeep.take();
    if (kept) {
        if (TABS.some((t) => t[0] === kept.tab)) tab = kept.tab;
        if (kept.sort) sort = kept.sort;
        $('clQ').value = kept.q || '';
    }
    if (window.cpKeep) cpKeep.track(() => ({ tab, sort, q: $('clQ').value }));
    renderCards();
    render();
    if (D.edit) {
        const c = clients.find((x) => x.id === D.edit);
        if (c) { openSheet(c, D.panel); if (D.saved) $('clSaved').textContent = 'انحفظ ✓'; history.replaceState(null, '', location.pathname); }
    }
})();
</script>
@endsection
