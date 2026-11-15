/*
 * خانة التاريخ لكل النظام (قرار 15/11/2026، سجل القرارات 17):
 * - الخانة بتبلّش بتاريخ اليوم، ومعها زر التقويم بس. زر «اليوم» بيطلع بس إذا التاريخ تغيّر عن اليوم.
 * - ↑/↓ بتزيد وبتنقص الجزء اللي الكيرسر عليه (يوم/شهر/سنة)، وبيضل الجزء محدد.
 * - التقويم نافذة صغيرة بشكل براند تحت الزر: الأسبوع بيبلّش السبت، اليوم بإطار، المختار وردي.
 *
 * الاستعمال: cpDate.html({ id, name, value, today, label }) بيرجّع HTML الحقل، والباقي شغّال لحاله
 * لأي <input data-cp-date> بالصفحة. خيارات على الـinput:
 *   data-today="D/M/YYYY"   تاريخ اليوم (افتراضي: تاريخ الجهاز)
 *   data-lock="YYYY-MM"     التقويم والأسهم مقفولين على هالشهر (نافذة الاشتراك)
 *   data-min-day / data-max-day   أيام مسموحة بالشهر المقفول، والأيام المحسوبة بتتلوّن بينهم
 * كل تغيير بيطلق حدث input عادي على الخانة.
 */
(function () {
    const pad = (n) => String(n).padStart(2, '0');
    const parse = (v) => {
        const m = String(v || '').trim().replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).match(/^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$/);
        if (!m) return null;
        const dt = new Date(+m[3], +m[2] - 1, +m[1]);
        return dt.getMonth() === +m[2] - 1 ? [dt.getFullYear(), dt.getMonth(), dt.getDate()] : null;
    };
    const fmt = (y, m, d) => `${d}/${m + 1}/${y}`;
    const dim = (y, m) => new Date(y, m + 1, 0).getDate();
    const nowLabel = () => { const t = new Date(); return fmt(t.getFullYear(), t.getMonth(), t.getDate()); };
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function todayOf(inp) { return inp.dataset.today || nowLabel(); }
    function lockOf(inp) { const l = inp.dataset.lock; if (!l) return null; const [y, m] = l.split('-').map(Number); return [y, m - 1]; }
    function bounds(inp) {
        const lk = lockOf(inp);
        const lo = +inp.dataset.minDay || 1;
        const hi = +inp.dataset.maxDay || (lk ? dim(lk[0], lk[1]) : 31);
        return [lo, hi];
    }
    function setValue(inp, v) {
        if (inp.value === v) return;
        inp.value = v;
        inp.dispatchEvent(new Event('input', { bubbles: true }));
        syncToday(inp);
    }
    function syncToday(inp) {
        const box = inp.closest('.cp-date');
        const b = box && box.querySelector('[data-cp-today]');
        if (b) b.hidden = !!inp.dataset.lock || inp.value.trim() === todayOf(inp);  // بالشهر المقفول «اليوم» ما إله معنى
    }

    /* ↑/↓ */
    function step(inp, dir) {
        const p = parse(inp.value) || parse(todayOf(inp));
        if (!p) return;
        let [y, m, d] = p;
        const v = inp.value;
        const pos = inp.selectionStart ?? 0, i1 = v.indexOf('/'), i2 = v.lastIndexOf('/');
        const lk = lockOf(inp);
        let seg = pos <= i1 ? 0 : pos <= i2 ? 1 : 2;
        if (lk) seg = 0;
        if (seg === 0) {
            if (lk) { const [lo, hi] = bounds(inp); d = Math.min(hi, Math.max(lo, d + dir)); } else { const x = new Date(y, m, d + dir); y = x.getFullYear(); m = x.getMonth(); d = x.getDate(); }
        } else {
            if (seg === 1) m += dir; else y += dir;
            const x = new Date(y, m, 1); y = x.getFullYear(); m = x.getMonth(); d = Math.min(d, dim(y, m));
        }
        const out = fmt(y, m, d);
        setValue(inp, out);
        const j1 = out.indexOf('/'), j2 = out.lastIndexOf('/');
        const r = seg === 0 ? [0, j1] : seg === 1 ? [j1 + 1, j2] : [j2 + 1, out.length];
        inp.setSelectionRange(r[0], r[1]);
    }

    /* التقويم */
    const DN = ['س', 'ح', 'ن', 'ث', 'ر', 'خ', 'ج'];
    let cal = null, st = null;
    function ensure() {
        if (cal) return cal;
        cal = document.createElement('div');
        cal.className = 'cp-cal';
        cal.hidden = true;
        cal.setAttribute('role', 'dialog');
        cal.setAttribute('aria-label', 'اختار التاريخ');
        document.body.appendChild(cal);
        cal.addEventListener('click', (e) => {
            e.stopPropagation();
            const nv = e.target.closest('[data-nv]');
            if (nv) { const d = new Date(st.view[0], st.view[1] + (+nv.dataset.nv), 1); st.view = [d.getFullYear(), d.getMonth()]; draw(); return; }
            const b = e.target.closest('[data-d]');
            if (b && !b.disabled) { pick(st.view[0], st.view[1], +b.dataset.d); return; }
            if (e.target.closest('[data-ctoday]')) { const t = parse(todayOf(st.inp)); pick(t[0], t[1], t[2]); }
        });
        cal.addEventListener('keydown', (e) => {
            const b = e.target.closest('[data-d]');
            if (!b) return;
            const k = { ArrowRight: -1, ArrowLeft: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
            if (!k) return;
            e.preventDefault();
            const n = +b.dataset.d + k;
            const t = cal.querySelector(`[data-d="${n}"]:not([disabled])`);
            if (t) t.focus();
        });
        return cal;
    }
    function draw() {
        const [y, m] = st.view;
        const n = dim(y, m), off = (new Date(y, m, 1).getDay() + 1) % 7;
        const sel = st.sel, t = parse(todayOf(st.inp)) || [];
        const [lo, hi] = bounds(st.inp);
        const lk = st.lock;
        const minD = +st.inp.dataset.minDay || 0, maxD = +st.inp.dataset.maxDay || 0;
        const rFrom = st.inp.dataset.rangeFrom ? +st.inp.dataset.rangeFrom : null;
        const rTo = st.inp.dataset.rangeTo ? +st.inp.dataset.rangeTo : null;
        let cells = '';
        for (let i = 0; i < off; i++) cells += '<span></span>';
        for (let d = 1; d <= n; d++) {
            const isSel = sel && sel[0] === y && sel[1] === m && sel[2] === d;
            const isT = t[0] === y && t[1] === m && t[2] === d;
            const dis = lk && (d < lo || d > hi);
            const from = rFrom ?? (sel ? sel[2] : 0), to = rTo ?? (sel ? sel[2] : 0);
            const inR = (rFrom !== null || rTo !== null) && d >= from && d <= to;
            cells += `<button type="button" data-d="${d}"${dis ? ' disabled' : ''} class="${isSel ? 'sel' : ''}${isT ? ' td' : ''}${inR && !isSel ? ' rg' : ''}">${d}</button>`;
        }
        const cnt = (rFrom !== null || rTo !== null) && sel ? (rTo ?? sel[2]) - (rFrom ?? sel[2]) + 1 : 0;
        void minD; void maxD;
        cal.innerHTML = `<div class="cp-cal-h">${lk ? '<span></span>' : '<button type="button" class="cp-cal-nv" data-nv="-1" aria-label="الشهر اللي قبل">›</button>'}<b dir="ltr">${m + 1}/${y}</b>${lk ? '<span></span>' : '<button type="button" class="cp-cal-nv" data-nv="1" aria-label="الشهر اللي بعد">‹</button>'}</div>`
            + `<div class="cp-cal-g">${DN.map((x) => `<i>${x}</i>`).join('')}${cells}</div>`
            + `<div class="cp-cal-f">${cnt > 0 ? `<span>${cnt} يوم محسوبين</span>` : '<span></span>'}${lk ? '' : '<button type="button" data-ctoday="1">اليوم</button>'}</div>`;
    }
    function open(inp, btn) {
        ensure();
        const sel = parse(inp.value) || parse(todayOf(inp));
        const lk = lockOf(inp);
        st = { inp, sel, lock: !!lk, view: lk ? lk : [sel[0], sel[1]] };
        draw();
        cal.hidden = false;
        const r = btn.getBoundingClientRect();
        const w = cal.offsetWidth, h = cal.offsetHeight;
        const vw = document.documentElement.clientWidth, vh = window.innerHeight;
        let left = r.right - w;
        if (left < 8) left = Math.min(r.left, vw - w - 8);
        let top = r.bottom + 6;
        if (top + h > vh - 8 && r.top - h - 6 > 8) top = r.top - h - 6;
        cal.style.left = Math.max(8, left) + 'px';
        cal.style.top = top + 'px';
        const f = cal.querySelector('.sel') || cal.querySelector('[data-d]:not([disabled])');
        if (f) f.focus();
    }
    function close(refocus) { if (!cal || cal.hidden) return; cal.hidden = true; if (refocus && st) st.inp.focus(); }
    function pick(y, m, d) { setValue(st.inp, fmt(y, m, d)); close(true); }

    document.addEventListener('keydown', (e) => {
        const inp = e.target.closest && e.target.closest('input[data-cp-date]');
        if (inp && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { e.preventDefault(); step(inp, e.key === 'ArrowUp' ? 1 : -1); return; }
        if (e.key === 'Escape' && cal && !cal.hidden) { e.stopPropagation(); close(true); }
    }, true);
    document.addEventListener('input', (e) => { const inp = e.target.closest && e.target.closest('input[data-cp-date]'); if (inp) syncToday(inp); });
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-cp-cal]');
        if (b) {
            e.preventDefault(); e.stopPropagation();
            const inp = b.closest('.cp-date').querySelector('input[data-cp-date]');
            if (cal && !cal.hidden && st && st.inp === inp) { close(false); return; }
            open(inp, b);
            return;
        }
        const t = e.target.closest('[data-cp-today]');
        if (t) { const inp = t.closest('.cp-date').querySelector('input[data-cp-date]'); setValue(inp, todayOf(inp)); inp.focus(); return; }
        if (cal && !cal.hidden && !e.target.closest('.cp-cal')) close(false);
    });
    window.addEventListener('resize', () => close(false));
    document.addEventListener('scroll', (e) => { if (cal && !cal.hidden && !(e.target && e.target.closest && e.target.closest('.cp-cal'))) close(false); }, true);

    window.cpDate = {
        parse, fmt, dim,
        /** حقل التاريخ كامل: الخانة + «اليوم» (مخفي إذا القيمة اليوم) + زر التقويم. */
        html(o) {
            const today = o.today || nowLabel();
            const val = o.value || today;
            const attrs = Object.entries(o.data || {}).map(([k, v]) => ` data-${k}="${esc(v)}"`).join('');
            return `<div class="cp-date"><input id="${esc(o.id)}"${o.name ? ` name="${esc(o.name)}"` : ''} data-cp-date data-today="${esc(today)}"${attrs} inputmode="numeric" dir="ltr" autocomplete="off" placeholder="يوم/شهر/سنة" value="${esc(val)}"${o.label ? ` aria-label="${esc(o.label)}"` : ''}>`
                + `<button type="button" class="cp-date-today" data-cp-today${val === today || (o.data && o.data.lock) ? ' hidden' : ''}>اليوم</button>`
                + `<button type="button" class="cp-date-cal" data-cp-cal aria-label="اختار من التقويم"><span class="material-symbols-outlined">calendar_month</span></button></div>`;
        },
        sync: syncToday,
        close,
    };
})();
