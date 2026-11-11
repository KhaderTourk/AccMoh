/*
 * الشهري (زبون / موظف / مورد) — صورة «مستحقات الموظفين والموردين» نسخة 5، معتمدة 11/11/2026:
 * - «أول شهر» من قائمة (6 شهور ورا و6 لقدّام)، ويوم البداية من شبكة أيام، افتراضياً يوم 1.
 * - المقترح = الاتفاق ÷ أيام الشهر الفعلية × أيام الشغل، بالكسور، و«مبلغ أول شهر» قابل للتعديل.
 * - «حفظ» ما بيضيف مبالغ: كل شهر بينضاف لحاله بزر «أضف».
 * - «وقّف»: آخر شهر من قائمة شهور الاشتراك + آخر يوم شغل. الشهر المنضاف بيتعدّل نفس الحركة،
 *   والشهور المنضافة بعده بتنشال.
 */
(function () {
    const pad = (n) => String(n).padStart(2, '0');
    const dim = (y, m) => new Date(y, m, 0).getDate();
    const fmt = (n) => (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const ml = (y, m) => m + '/' + y;
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const round2 = (n) => Math.round(n * 100) / 100;

    function dd(id, label) {
        return `<div class="mp-dd"><button type="button" class="mp-dd-btn" id="${id}" aria-haspopup="listbox">${esc(label)}</button><div class="cl-menu mp-menu mp-dd-menu" id="${id}Menu" role="listbox" hidden></div></div>`;
    }
    function dayGrid(host, y, m, sel, inRange, minDay) {
        const n = dim(y, m);
        let h = '';
        for (let d = 1; d <= n; d++) {
            const off = minDay && d < minDay;
            h += `<button type="button" data-d="${d}" class="${d === sel ? 'on' : (inRange(d) ? 'in' : '')}"${off ? ' disabled' : ''}>${d}</button>`;
        }
        host.innerHTML = h;
    }
    function menu(btn, list, onPick) {
        const box = document.getElementById(btn.id + 'Menu');
        box.innerHTML = list.map((x, i) => `<button type="button" role="option" data-i="${i}" class="${x.on ? 'on' : ''}">${x.html}</button>`).join('');
        box.onclick = (e) => { const b = e.target.closest('[data-i]'); if (!b) return; box.hidden = true; onPick(+b.dataset.i); };
        btn.onclick = (e) => { e.stopPropagation(); const open = box.hidden; document.querySelectorAll('.mp-dd-menu').forEach((x) => { x.hidden = true; }); box.hidden = !open; };
    }
    document.addEventListener('click', (e) => { if (!e.target.closest('.mp-dd')) document.querySelectorAll('.mp-dd-menu').forEach((x) => { x.hidden = true; }); });

    /* ——— نافذة الشهري ——— */
    function fields(o) {
        return `<div class="cl-field"><label for="mAmt">${esc(o.amtLabel)}</label><input id="mAmt" name="amount" inputmode="decimal" dir="ltr" placeholder="0.00"><small class="cs-hint" id="mAmtHint"></small></div>
            <div class="cl-field"><label>أول شهر</label>${dd('mMonth', '')}</div>
            <div class="cl-field"><label id="mDayL"></label><div class="mp-days" id="mDays"></div></div>
            <div class="mp-calc" id="mCalc"></div>
            <div class="cl-field"><label for="mFirst">مبلغ أول شهر <button type="button" class="mp-link" id="mReset" hidden>رجّع المقترح</button></label>
                <input id="mFirst" name="first_amount" inputmode="decimal" dir="ltr"></div>
            <input type="hidden" id="mStart" name="starts_on">
            <p class="st-note mp-hint" id="mHint"></p>`;
    }
    function wire(o) {
        const t = new Date(o.today + 'T00:00:00');
        const months = [];
        for (let k = -6; k <= 6; k++) { const d = new Date(t.getFullYear(), t.getMonth() + k, 1); months.push([d.getFullYear(), d.getMonth() + 1]); }
        let ix = 6, day = 1, edited = false;
        const $ = (id) => document.getElementById(id);
        if (o.amount) $('mAmt').value = o.amount;
        function paint() {
            const [y, m] = months[ix];
            const D = dim(y, m);
            if (day > D) day = D;
            menu($('mMonth'), months.map((x, i) => ({ html: ml(x[0], x[1]), on: i === ix })), (i) => { ix = i; edited = false; paint(); });
            $('mMonth').textContent = ml(y, m);
            $('mDayL').textContent = o.fromLabel + ' بشهر ' + ml(y, m);
            dayGrid($('mDays'), y, m, day, (d) => d > day);
            $('mDays').onclick = (e) => { const b = e.target.closest('[data-d]'); if (!b) return; day = +b.dataset.d; edited = false; paint(); };
            $('mStart').value = day + '/' + m + '/' + y;
            const a = parseFloat($('mAmt').value) || 0;
            const days = D - day + 1;
            const sug = round2(days >= D ? a : a / D * days);
            $('mCalc').innerHTML = days < D
                ? `<div><span>أيام الشغل بشهر ${ml(y, m)}</span><b>${days} من ${D}</b></div><div><span>المقترح: ${fmt(a)} ÷ ${D} × ${days}</span><b>${fmt(sug)} شيكل</b></div>`
                : `<div><span>شهر ${ml(y, m)} كامل</span><b>${fmt(sug)} شيكل</b></div>`;
            if (!edited) $('mFirst').value = a ? sug.toFixed(2) : '';
            $('mReset').hidden = !edited;
            $('mHint').textContent = `«حفظ» ما بيضيف ولا مبلغ. أول شهر بيطلع بفلتر «${o.listName}» بالمبلغ اللي فوق، وكل شهر بعده بـ${o.amtLabel === 'الراتب الشهري' ? 'الراتب' : 'الاتفاق'} الشهري، وبتضيف شهر بشهر بزر «أضف».`;
            if (o.onChange) o.onChange();
        }
        $('mAmt').addEventListener('input', () => { edited = false; paint(); });
        $('mFirst').addEventListener('input', () => { edited = true; $('mReset').hidden = false; });
        $('mReset').addEventListener('click', () => { edited = false; paint(); });
        paint();
        return { valid: () => parseFloat($('mAmt').value) > 0 && parseFloat($('mFirst').value) >= 0 };
    }

    /* ——— «وقّف» ——— */
    // sub: {title, amount, from, added:{'Y-m': amount}, stop}; o: {today, noun}
    function openStop(sub, o) {
        const $ = (id) => document.getElementById(id);
        const [fy, fm, fd] = sub.from.split('-').map(Number);
        const t = new Date(o.today + 'T00:00:00');
        const months = [];
        let y = fy, m = fm;
        const lastY = t.getFullYear(), lastM = t.getMonth() + 1;
        while (y < lastY || (y === lastY && m <= lastM)) { months.push([y, m]); m++; if (m > 12) { m = 1; y++; } }
        if (!months.length) months.push([fy, fm]);
        const added = sub.added || {};
        const key = (x) => x[0] + '-' + pad(x[1]);
        let ix = months.length - 1, day = 0, edited = false;
        $('mpStopTitle').textContent = 'وقّف ' + o.noun + ' · ' + sub.title;
        $('mpStopWho').textContent = fmt(sub.amount) + ' شيكل بالشهر · من ' + fd + '/' + fm + '/' + fy;
        $('mpStopNotes').value = '';
        function suggest() {
            const [yy, mm] = months[ix];
            const D = dim(yy, mm);
            const start = (yy === fy && mm === fm) ? fd : 1;
            const n = Math.max(0, day - start + 1);
            return { D, start, n, amt: n >= D ? sub.amount : round2(sub.amount / D * n) };
        }
        function paint() {
            const [yy, mm] = months[ix];
            const { D, start, n, amt } = suggest();
            if (!day || day > D) day = Math.min(D, Math.max(start, (yy === t.getFullYear() && mm === t.getMonth() + 1) ? t.getDate() : D));
            if (day < start) day = start;
            const s = suggest();
            menu($('mpStopMonth'), months.map((x, i) => ({ html: ml(x[0], x[1]) + (added[key(x)] !== undefined ? ' <small>انضاف</small>' : ''), on: i === ix })), (i) => { ix = i; day = 0; edited = false; paint(); });
            $('mpStopMonth').textContent = ml(yy, mm);
            $('mpStopDayL').textContent = 'آخر يوم شغل بشهر ' + ml(yy, mm);
            dayGrid($('mpStopDays'), yy, mm, day, (d) => d >= s.start && d < day, s.start);
            $('mpStopDays').onclick = (e) => { const b = e.target.closest('[data-d]'); if (!b || b.disabled) return; day = +b.dataset.d; edited = false; paint(); };
            if (!edited) $('mpStopAmt').value = s.amt.toFixed(2);
            const val = parseFloat($('mpStopAmt').value) || 0;
            const was = added[key(months[ix])];
            let c = `<div><span>أيام الشغل بشهر ${ml(yy, mm)}</span><b>${s.n} من ${s.D}</b></div><div><span>المقترح: ${fmt(sub.amount)} ÷ ${s.D} × ${s.n}</span><b>${fmt(s.amt)} شيكل</b></div>`;
            if (was !== undefined) c += `<div><span>كان منضاف لهالشهر</span><b>${fmt(was)} شيكل</b></div><div class="t"><span>بعد التعديل</span><b>${fmt(val)} شيكل</b></div>`;
            const later = months.slice(ix + 1).filter((x) => added[key(x)] !== undefined).map((x) => ml(x[0], x[1]));
            if (later.length) c += `<p class="st-note">الشهور المنضافة بعده (${later.join('، ')}) بتنشال من الكشف.</p>`;
            $('mpStopCalc').innerHTML = c;
            $('mpStopAmtL').textContent = was !== undefined ? 'مبلغ ' + ml(yy, mm) + ' بعد التعديل' : 'مبلغ آخر شهر';
            $('mpStopSave').textContent = was !== undefined ? 'وقّف وعدّل الشهر' : 'وقّف وأضف ' + fmt(val);
            $('mpStopDate').value = day + '/' + mm + '/' + yy;
        }
        $('mpStopAmt').oninput = () => { edited = true; paint(); };
        $('mpStopForm').action = sub.stop;
        $('mpStopForm').onsubmit = (e) => {
            if (!(parseFloat($('mpStopAmt').value) >= 0)) { e.preventDefault(); return; }
            const later = months.slice(ix + 1).filter((x) => added[key(x)] !== undefined).length;
            if (later && !e.submitter?.dataset.ok) {
                e.preventDefault();
                const msg = `بينشال ${later === 1 ? 'شهر منضاف' : later + ' شهور منضافة'} بعد شهر التوقيف. أكمّل؟`;
                const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
                ask.then((ok) => { if (ok) { $('mpStopForm').onsubmit = null; $('mpStopForm').submit(); } });
            }
        };
        $('mpStopCancel').onclick = () => { $('mpStopSheet').hidden = true; };
        $('mpStopSheet').onclick = (e) => { if (e.target.id === 'mpStopSheet') $('mpStopSheet').hidden = true; };
        paint();
        $('mpStopSheet').hidden = false;
    }

    window.cpMonthlySheet = { fields, wire, openStop };
})();
