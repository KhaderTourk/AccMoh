{{--
    زر «تحويل» بقائمة: «تحويل بين الأدراج» و«تحويل عملات» (داخل نفس الدرج).
    كل خيار بيفتح نافذة خطوات (صورة «الأدراج والأرصدة» المعتمدة 12/11/2026):
    اختيار الدرج بينقلك مباشرة، العملات والمبلغ وسعر الصرف بشاشة وحدة، وآخر شاشة ملخص للتأكيد.
    الحفظ عبر cp.transfers.store نفسه (نفس التحقق والقيود)، وبيرجعك لنفس الصفحة.
    $wizard = ['methods' => [['id','name','bal' => [code => amount]]], 'currencies' => [['id','code','name','symbol']]]
--}}
<div class="tw-wrap">
    <button type="button" class="cp-btn cp-btn-primary tw-btn" id="twBtn" aria-haspopup="menu" aria-expanded="false">
        @include('cp.partials.bs-icon', ['name' => 'transfer', 'size' => 18]) <span>تحويل</span> @include('cp.partials.bs-icon', ['name' => 'chevron', 'size' => 14])
    </button>
    <div class="cl-menu tw-menu" id="twMenu" role="menu" hidden>
        <button type="button" role="menuitem" data-tw="move">
            <span class="tw-mi">@include('cp.partials.bs-icon', ['name' => 'transfer', 'size' => 18])</span>
            <span><b>تحويل بين الأدراج</b><small>من درج لدرج ثاني</small></span>
        </button>
        <button type="button" role="menuitem" data-tw="fx">
            <span class="tw-mi">@include('cp.partials.bs-icon', ['name' => 'exchange', 'size' => 18])</span>
            <span><b>تحويل عملات</b><small>داخل نفس الدرج، من عملة لعملة</small></span>
        </button>
    </div>
</div>

@push('scripts')
<div class="cl-sheet tw-sheet" id="twSheet" hidden>
    <form class="tw-dialog" id="twForm" method="post" action="{{ route('cp.transfers.store') }}" role="dialog" aria-modal="true" aria-labelledby="twTitle" novalidate>
        @csrf
        <input type="hidden" name="stay" value="1">
        <input type="hidden" name="from_payment_method_id" id="twFFrom">
        <input type="hidden" name="to_payment_method_id" id="twFTo">
        <input type="hidden" name="currency_id" id="twFCur">
        <input type="hidden" name="to_currency_id" id="twFToCur">
        <input type="hidden" name="amount" id="twFAmount">
        <input type="hidden" name="to_amount" id="twFToAmount">

        <div class="tw-head">
            <h2 id="twTitle">تحويل بين الأدراج</h2>
            <button type="button" class="tw-x" id="twClose" aria-label="إغلاق">@include('cp.partials.bs-icon', ['name' => 'close', 'size' => 18])</button>
        </div>
        <div class="tw-bar" id="twBar"></div>
        <div class="tw-chips" id="twChips"></div>
        <div class="tw-body" id="twBody"></div>
        <div class="tw-extra" id="twExtra" hidden>
            <div class="tw-f"><label>التاريخ</label>@include('cp.partials.date-field', ['name' => 'transfer_date', 'value' => date('Y-m-d'), 'required' => true, 'class' => 'tw-in'])</div>
            <div class="tw-f"><label for="twNotes">ملاحظة</label><input id="twNotes" name="notes" class="tw-in" placeholder="اختياري" maxlength="500"></div>
        </div>
        <div class="tw-foot" id="twFoot"></div>

        <div class="tw-ask" id="twAsk" hidden>
            <div class="tw-askbox" role="alertdialog" aria-labelledby="twAskT">
                <h3 id="twAskT">بدك تلغي التحويل؟</h3>
                <p>اللي عبّيته ما رح ينحفظ.</p>
                <div class="tw-foot">
                    <button type="button" class="tw-back" id="twAskNo">لأ، كمّل</button>
                    <button type="button" class="tw-pri" id="twAskYes">إلغاء التحويل</button>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
(function () {
    const W = @json($wizard);
    const RATES = @json(\App\Support\Rates::today());
    const ICON_BACK = @json(view('cp.partials.bs-icon', ['name' => 'back', 'size' => 16])->render());
    const ICON_CHECK = @json(view('cp.partials.bs-icon', ['name' => 'check', 'size' => 12])->render());
    const $ = (id) => document.getElementById(id);
    const cur = (code) => W.currencies.find((c) => c.code === code) || { code, name: code, symbol: '' };
    const meth = (id) => W.methods.find((m) => m.id === id);
    const esc = (s) => String(s).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const fmt = (v) => (v < 0 ? '−' : '') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (code, v) => '<span class="bl-money" dir="ltr">' + esc(cur(code).symbol || code) + ' ' + fmt(v) + '</span>';
    const num = (s) => { const t = String(s || '').replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/,/g, '').replace('٫', '.').trim(); const v = parseFloat(t); return t !== '' && isFinite(v) ? v : NaN; };
    const r2 = (v) => Math.round(v * 100) / 100;
    const balLine = (m) => { const parts = W.currencies.filter((c) => Math.abs(m.bal[c.code] || 0) >= 0.005).map((c) => money(c.code, m.bal[c.code])); return parts.length ? parts.join(' · ') : 'فاضي'; };

    let S = null;
    function steps() { return S.mode === 'move' ? ['from', 'to', 'money', 'review'] : ['from', 'money', 'review']; }
    function step() { return steps()[S.i]; }

    function start(mode) {
        S = { mode, i: 0, from: null, to: null, send: null, recv: null, amount: '', rate: '' };
        $('twTitle').textContent = mode === 'move' ? 'تحويل بين الأدراج' : 'تحويل عملات';
        $('twAskT').textContent = mode === 'move' ? 'بدك تلغي التحويل؟' : 'بدك تلغي الصرف؟';
        $('twAskYes').textContent = mode === 'move' ? 'إلغاء التحويل' : 'إلغاء الصرف';
        $('twNotes').value = '';
        $('twAsk').hidden = true;
        $('twSheet').hidden = false;
        render();
    }
    function close() { $('twSheet').hidden = true; S = null; }
    function askClose() { if (!S || S.from === null) { close(); return; } $('twAsk').hidden = false; $('twAskNo').focus(); }

    function defaults() {
        const m = meth(S.from), has = (c) => (m.bal[c] || 0) > 0.004;
        const codes = W.currencies.map((c) => c.code);
        if (S.mode === 'move') {
            if (!S.send || !codes.includes(S.send)) S.send = has('ILS') ? 'ILS' : (codes.find(has) || 'ILS');
            if (!S.recv) S.recv = S.send;
        } else {
            if (!S.send) S.send = codes.find((c) => c !== 'ILS' && has(c)) || 'ILS';
            if (!S.recv || S.recv === S.send) S.recv = S.send === 'ILS' ? (codes.find((c) => c !== 'ILS') || 'USD') : 'ILS';
        }
    }

    /** سعر الصرف كما بيحكيه الناس: كم شيكل بالدولار/الدينار، أو كم دولار بالدينار. */
    function rateInfo() {
        const a = S.send, b = S.recv;
        let strong, weak;
        if (a === 'ILS') { strong = b; weak = a; } else if (b === 'ILS') { strong = a; weak = b; } else { strong = (a === 'JOD' || b === 'JOD') ? 'JOD' : a; weak = strong === a ? b : a; }
        const today = weak === 'ILS' ? RATES[strong] : (RATES[strong] && RATES[weak] ? RATES[strong] / RATES[weak] : null);
        return { label: cur(strong).name + ' بكم ' + cur(weak).name, mul: S.send === strong, today };
    }
    function calc() {
        const amt = num(S.amount), fx = S.send !== S.recv;
        const avail = meth(S.from).bal[S.send] || 0;
        let to = amt, err = '';
        if (!(amt > 0)) err = ' ';
        else if (amt > avail + 0.004) err = 'المبلغ أكتر من المتوفر.';
        if (fx) {
            const rate = num(S.rate), ri = rateInfo();
            to = rate > 0 && amt > 0 ? r2(ri.mul ? amt * rate : amt / rate) : NaN;
            if (!err && !(rate > 0)) err = ' ';
            if (!err && !(to > 0)) err = 'المبلغ الواصل صغير كتير.';
        }
        return { amt: r2(amt), to: fx ? to : r2(amt), fx, err, avail };
    }

    function bar() {
        const n = steps().length;
        $('twBar').style.gridTemplateColumns = 'repeat(' + n + ', minmax(0, 1fr))';
        $('twBar').innerHTML = Array.from({ length: n }, (_, k) => '<i class="' + (k <= S.i ? 'on' : '') + '"></i>').join('');
    }
    function chips() {
        const c = [];
        const s = step();
        if (S.mode === 'move') {
            if (s !== 'from' && s !== 'review' && S.from !== null) c.push(['from', 'من: ' + meth(S.from).name]);
            if (s === 'money' && S.to !== null) c.push(['to', 'لـ: ' + meth(S.to).name]);
        } else if (s === 'money' && S.from !== null) c.push(['from', 'الدرج: ' + meth(S.from).name]);
        $('twChips').innerHTML = c.map(([k, t]) => '<button type="button" class="tw-chip" data-go="' + k + '">' + esc(t) + '</button>').join('');
    }
    function backBtn() { return '<button type="button" class="tw-back" data-back>' + ICON_BACK + '<span>رجوع</span></button>'; }

    function render() {
        bar(); chips();
        const s = step();
        $('twExtra').hidden = s !== 'review';
        const body = $('twBody'), foot = $('twFoot');
        if (s === 'from' || s === 'to') {
            const q = s === 'from' ? (S.mode === 'move' ? 'من أي درج؟' : 'بأي درج؟') : 'لأي درج؟';
            const sel = s === 'from' ? S.from : S.to;
            const list = W.methods.filter((m) => s === 'from' || m.id !== S.from);
            body.innerHTML = '<h3 class="tw-q">' + q + '</h3>' + list.map((m) => '<button type="button" class="tw-opt ' + (m.id === sel ? 'sel' : '') + '" data-pick="' + m.id + '"><span><b>' + esc(m.name) + '</b><small>' + balLine(m) + '</small></span><span class="tw-ck">' + (m.id === sel ? ICON_CHECK : '') + '</span></button>').join('');
            foot.innerHTML = S.i === 0 ? '' : backBtn();
            foot.hidden = S.i === 0;
            return;
        }
        foot.hidden = false;
        if (s === 'money') {
            defaults();
            const seg = (key, val, disabledCode) => '<div class="tw-seg">' + W.currencies.map((c) => '<button type="button" class="' + (c.code === val ? 'on' : '') + '" data-' + key + '="' + c.code + '"' + (c.code === disabledCode ? ' disabled' : '') + '>' + esc(c.name) + '</button>').join('') + '</div>';
            const ri = S.send !== S.recv ? rateInfo() : null;
            body.innerHTML = '<h3 class="tw-q">' + (S.mode === 'move' ? 'كم وبأي عملة؟' : 'كم بدك تصرّف؟') + '</h3>'
                + '<span class="tw-l">' + (S.mode === 'move' ? 'بتبعت' : 'من') + '</span>' + seg('send', S.send)
                + '<input class="tw-big" id="twAmt" inputmode="decimal" dir="ltr" placeholder="0.00" aria-label="المبلغ" value="' + esc(S.amount) + '">'
                + '<small class="tw-hint" id="twAvail"></small>'
                + '<span class="tw-l">' + (S.mode === 'move' ? 'بيوصل' : 'إلى') + '</span>' + seg('recv', S.recv, S.mode === 'fx' ? S.send : null)
                + (ri ? '<div class="tw-two"><div class="tw-f"><label for="twRate">' + esc(ri.label) + '</label><input class="tw-mid" id="twRate" inputmode="decimal" dir="ltr" placeholder="' + (ri.today ? 'سعر اليوم ' + (+ri.today.toFixed(4)) : '0.00') + '" value="' + esc(S.rate) + '"></div>'
                    + '<div class="tw-f"><label>' + (S.mode === 'move' ? 'بيوصل ' + esc(meth(S.to).name) : 'بتاخد') + '</label><div class="tw-mid tw-out" id="twOut"></div></div></div>' : '')
                + '<small class="tw-hint tw-err" id="twErr"></small>';
            foot.innerHTML = backBtn() + '<button type="button" class="tw-pri" data-next disabled>التالي</button>';
            update();
            setTimeout(() => { const a = $('twAmt'); if (a && !S.amount) a.focus(); }, 30);
            return;
        }
        // review
        const c = calc();
        const from = meth(S.from), to = S.mode === 'move' ? meth(S.to) : from;
        const rows = [];
        if (S.mode === 'move') {
            rows.push(['من', esc(from.name) + ' · ' + money(S.send, c.amt)]);
            rows.push(['لـ', esc(to.name) + ' · ' + money(S.recv, c.to)]);
        } else {
            rows.push(['الدرج', esc(from.name)]);
            rows.push(['بيطلع', money(S.send, c.amt)]);
            rows.push(['بيدخل', money(S.recv, c.to)]);
        }
        if (c.fx) rows.push(['سعر الصرف', '<span dir="ltr">' + esc(rateInfo().label) + ': ' + esc(String(num(S.rate))) + '</span>']);
        const after = S.mode === 'move'
            ? esc(from.name) + ' ' + money(S.send, (from.bal[S.send] || 0) - c.amt) + ' · ' + esc(to.name) + ' ' + money(S.recv, (to.bal[S.recv] || 0) + c.to)
            : money(S.recv, (from.bal[S.recv] || 0) + c.to) + ' · ' + money(S.send, (from.bal[S.send] || 0) - c.amt);
        rows.push([S.mode === 'move' ? 'بعد التحويل' : esc(from.name) + ' بعد الصرف', after]);
        body.innerHTML = '<h3 class="tw-q">' + (S.mode === 'move' ? 'ملخص التحويل' : 'ملخص الصرف') + '</h3>' + rows.map(([k, v]) => '<div class="tw-rv"><span>' + k + '</span><span>' + v + '</span></div>').join('');
        foot.innerHTML = backBtn() + '<button type="submit" class="tw-pri tw-grow">تأكيد</button>';
    }

    function update() {
        const c = calc(), ri = c.fx ? rateInfo() : null;
        $('twAvail').innerHTML = 'متوفر ب' + esc(meth(S.from).name) + ' ' + money(S.send, c.avail);
        if ($('twOut')) $('twOut').innerHTML = c.to > 0 ? money(S.recv, c.to) : '—';
        $('twErr').textContent = c.err.trim();
        const nb = $('twFoot').querySelector('[data-next]'); if (nb) nb.disabled = !!c.err;
        if (ri && $('twOut')) $('twOut').title = ri.label;
    }

    function go(k) { const i = steps().indexOf(k); if (i >= 0 && i < S.i) { S.i = i; render(); } }

    $('twBtn').addEventListener('click', (e) => { e.stopPropagation(); const m = $('twMenu'); m.hidden = !m.hidden; $('twBtn').setAttribute('aria-expanded', String(!m.hidden)); });
    document.addEventListener('click', (e) => { if (!e.target.closest('.tw-wrap')) { $('twMenu').hidden = true; $('twBtn').setAttribute('aria-expanded', 'false'); } });
    $('twMenu').addEventListener('click', (e) => { const b = e.target.closest('[data-tw]'); if (!b) return; $('twMenu').hidden = true; start(b.dataset.tw); });

    $('twSheet').addEventListener('click', (e) => {
        if (!S) return;
        const t = e.target;
        if (t.id === 'twSheet') { askClose(); return; }
        const pick = t.closest('[data-pick]');
        if (pick) {
            const id = +pick.dataset.pick;
            if (step() === 'from') { if (S.from !== id) { S.from = id; if (S.to === id) S.to = null; S.send = null; S.recv = null; } }
            else { S.to = id; }
            S.i++; render(); return;
        }
        const send = t.closest('[data-send]');
        if (send) { S.send = send.dataset.send; if (S.mode === 'move' || S.recv === S.send) S.recv = S.mode === 'move' ? S.send : null; render(); return; }
        const recv = t.closest('[data-recv]');
        if (recv && !recv.disabled) { S.recv = recv.dataset.recv; render(); return; }
        if (t.closest('[data-next]')) { if (!calc().err) { S.i++; render(); } return; }
        if (t.closest('[data-back]')) { S.i = Math.max(0, S.i - 1); render(); return; }
        const chip = t.closest('[data-go]'); if (chip) { go(chip.dataset.go === 'to' ? 'to' : 'from'); }
    });
    $('twBody').addEventListener('input', (e) => {
        if (e.target.id === 'twAmt') S.amount = e.target.value;
        if (e.target.id === 'twRate') S.rate = e.target.value;
        update();
    });
    $('twBody').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); const nb = $('twFoot').querySelector('[data-next]'); if (nb && !nb.disabled) nb.click(); } });
    $('twClose').addEventListener('click', askClose);
    $('twAskNo').addEventListener('click', () => { $('twAsk').hidden = true; });
    $('twAskYes').addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && S && !$('twSheet').hidden) { if (!$('twAsk').hidden) $('twAsk').hidden = true; else askClose(); } });

    $('twForm').addEventListener('submit', (e) => {
        if (!S || step() !== 'review') { e.preventDefault(); return; }
        const c = calc();
        if (c.err) { e.preventDefault(); return; }
        $('twFFrom').value = S.from;
        $('twFTo').value = S.mode === 'move' ? S.to : S.from;
        $('twFCur').value = cur(S.send).id;
        $('twFToCur').value = cur(S.recv).id;
        $('twFAmount').value = c.amt.toFixed(2);
        $('twFToAmount').value = c.fx ? c.to.toFixed(2) : '';
        $('twFoot').querySelectorAll('button').forEach((b) => { b.disabled = true; });
    });
})();
</script>
@endpush
