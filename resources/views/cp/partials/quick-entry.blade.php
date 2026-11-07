{{-- «＋ تسجيل سريع» (تسليم 1/11 §11): زر أول بالقائمة واختصاره N، وزر عائم على الجوال.
     بعد اختيار الحركة والحساب بيفتح صفحة الطرف بنفس نافذة الحركة الموجودة. --}}
<button type="button" class="qe-fab" id="qeFab" aria-label="تسجيل سريع"><span class="material-symbols-outlined">add</span></button>
<div class="cl-sheet qe-sheet" id="qeSheet" hidden>
    <div class="cl-dialog qe-dialog" role="dialog" aria-modal="true" aria-labelledby="qeTitle">
        <h2 id="qeTitle">تسجيل سريع</h2>
        <div id="qeStep1">
            @foreach([
                'مصاري' => [['in', 'south_west', 'دخلت مصاري', 'من زبون أو شخص'], ['out', 'north_east', 'طلعت مصاري', 'لموظف، مورد، شخص، أو استرداد لزبون']],
                'بضاعة' => [['take', 'inventory_2', 'أخدت بضاعة', 'من زبون أو مورد'], ['give', 'local_shipping', 'أعطيت بضاعة', 'لزبون أو مورد']],
                'تحويل' => [['transfer', 'swap_horiz', 'تحويل رصيد', 'من حساب جهة لجهة ثانية'], ['drawers', 'account_balance_wallet', 'تحويل بين الأدراج', 'أو صرف عملة']],
            ] as $group => $ops)
                <p class="qe-group">{{ $group }}</p>
                <div class="qe-ops">
                    @foreach($ops as [$op, $icon, $label, $hint])
                        <button type="button" data-op="{{ $op }}"><span class="material-symbols-outlined">{{ $icon }}</span><b>{{ $label }}</b><small>{{ $hint }}</small></button>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div id="qeStep2" hidden>
            <div class="cl-seg qe-types" id="qeTypes"></div>
            <label class="cl-search"><span class="material-symbols-outlined">search</span><input id="qeQ" type="search" placeholder="ابحث بالاسم" autocomplete="off"></label>
            <div class="qe-list" id="qeList"></div>
        </div>
        <div class="cl-actions"><button type="button" class="cp-btn cp-btn-ghost" id="qeCancel">إلغاء</button></div>
    </div>
</div>
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const URL_ACC = @json(route('cp.quick.accounts'));
    const TYPES = { all: 'الكل', client: 'زبائن', worker: 'موظفين', supplier: 'موردين', person: 'أشخاص' };
    const ALLOW = { in: ['client', 'person'], out: ['worker', 'supplier', 'person', 'client'], take: ['client', 'supplier'], give: ['client', 'supplier'], transfer: ['person'] };
    const TITLE = { in: 'دخلت مصاري', out: 'طلعت مصاري', take: 'أخدت بضاعة', give: 'أعطيت بضاعة', transfer: 'تحويل رصيد' };
    const norm = (s) => String(s || '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').trim().toLowerCase();
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    let data = null, op = null, type = 'all';
    const recent = () => { try { return JSON.parse(localStorage.getItem('cpQuickRecent') || '[]'); } catch (e) { return []; } };
    function open() {
        op = null; $('qeTitle').textContent = 'تسجيل سريع'; $('qeStep1').hidden = false; $('qeStep2').hidden = true; $('qeSheet').hidden = false;
        if (!data) fetch(URL_ACC, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => r.json()).then((j) => { data = j; if (op) list(); });
    }
    function close() { $('qeSheet').hidden = true; }
    function list() {
        if (!data) { $('qeList').innerHTML = '<p class="cl-empty">بتتحمّل الحسابات…</p>'; return; }
        const allow = ALLOW[op];
        $('qeTypes').innerHTML = ['all'].concat(allow).map((t) => `<button type="button" data-t="${t}" class="${t === type ? 'on' : ''}">${TYPES[t]}</button>`).join('');
        const q = norm($('qeQ').value);
        let rows = data.accounts.filter((a) => allow.includes(a.type) && (type === 'all' || a.type === type) && (!q || norm(a.name).includes(q)));
        const rec = recent();
        rows.sort((a, b) => ((rec.indexOf(a.key) + 1 || 999) - (rec.indexOf(b.key) + 1 || 999)) || a.name.localeCompare(b.name, 'ar'));
        $('qeList').innerHTML = rows.slice(0, 60).map((a) => `<button type="button" data-k="${esc(a.key)}"><b>${esc(a.name)}</b><small>${TYPES[a.type]}${rec.includes(a.key) ? ' · مستعمل مؤخراً' : ''}</small></button>`).join('')
            || '<p class="cl-empty">ما في حساب بهالاسم.</p>';
    }
    document.addEventListener('click', (e) => {
        if (e.target.closest('#qeFab, [data-quick-open]')) { e.preventDefault(); open(); return; }
    });
    $('qeStep1').addEventListener('click', (e) => {
        const b = e.target.closest('[data-op]'); if (!b) return;
        if (b.dataset.op === 'drawers') { location.href = data ? data.transfer : @json(route('cp.transfers.create')); return; }
        op = b.dataset.op; type = 'all'; $('qeQ').value = '';
        $('qeTitle').textContent = TITLE[op] + ' · اختار الحساب';
        $('qeStep1').hidden = true; $('qeStep2').hidden = false; list(); setTimeout(() => $('qeQ').focus(), 30);
    });
    $('qeTypes').addEventListener('click', (e) => { const b = e.target.closest('[data-t]'); if (b) { type = b.dataset.t; list(); } });
    $('qeQ').addEventListener('input', list);
    $('qeList').addEventListener('click', (e) => {
        const b = e.target.closest('[data-k]'); if (!b) return;
        const a = data.accounts.find((x) => x.key === b.dataset.k);
        try { localStorage.setItem('cpQuickRecent', JSON.stringify([a.key].concat(recent().filter((k) => k !== a.key)).slice(0, 6))); } catch (e2) {}
        location.href = a.url + '?quick=' + op;
    });
    $('qeCancel').addEventListener('click', close);
    $('qeSheet').addEventListener('click', (e) => { if (e.target.id === 'qeSheet') close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !$('qeSheet').hidden) { close(); return; }
        const t = e.target;
        if ((e.key === 'n' || e.key === 'N' || e.key === 'ى') && !e.ctrlKey && !e.metaKey && !e.altKey
            && !(t.closest && t.closest('input, textarea, select, [contenteditable]')) && document.querySelectorAll('.cl-sheet:not([hidden])').length === 0) {
            e.preventDefault(); open();
        }
    });
})();
</script>
