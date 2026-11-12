{{--
    فلتر الفترة اللي بيغيّر الرابط (?from=&to= بصيغة YYYY-MM-DD). بيستخدم period-filter.
    المتغيرات: $from, $to (ISO أو null)، $base (رابط الصفحة بدون from/to).
    بدون إعادة تحميل (12/11/2026): بيمشي عبر cpSoft، والرابط الحالي هو مصدر باقي الفلاتر، والعنوان بيتحدّث بعد كل تبديل.
--}}
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const today = @json(now()->toDateString());
    let from = @json($from), to = @json($to);
    const iso = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const now = new Date(today + 'T00:00:00'), y = now.getFullYear(), m = now.getMonth();
    const P = [['كل الوقت', null, null], ['هالشهر', iso(new Date(y, m, 1)), iso(new Date(y, m + 1, 0))], ['الشهر الماضي', iso(new Date(y, m - 1, 1)), iso(new Date(y, m, 0))],
        ['آخر 3 شهور', iso(new Date(y, m - 2, 1)), iso(new Date(y, m + 1, 0))], ['هالسنة', iso(new Date(y, 0, 1)), iso(new Date(y, 11, 31))], ['السنة الماضية', iso(new Date(y - 1, 0, 1)), iso(new Date(y - 1, 11, 31))]];
    const go = (f, t) => {
        const u = new URL(location.href); u.hash = ''; ['page', 'from', 'to'].forEach((k) => u.searchParams.delete(k));
        if (f) u.searchParams.set('from', f); if (t) u.searchParams.set('to', t);
        $('csRangeMenu').hidden = true;
        if (window.cpSoft) cpSoft.go(u.toString()); else location.href = u.toString();
    };
    let hit;
    const render = () => {
        hit = P.find((p) => p[1] === from && p[2] === to);
        $('csRangeTxt').textContent = !from && !to ? 'كل الوقت' : (hit ? hit[0] : cpPeriod.label(from || '') + ' – ' + cpPeriod.label(to || today));
        $('csRangeBtn').classList.toggle('on', !!(from || to));
        $('csPresets').innerHTML = P.map((p, i) => `<button type="button" data-i="${i}" class="${(p === hit || (!from && !to && i === 0)) ? 'on' : ''}">${p[0]}</button>`).join('');
    };
    render();
    document.addEventListener('cp:soft', () => {
        const q = new URL(location.href).searchParams; from = q.get('from') || null; to = q.get('to') || null; render();
        if (!from || hit) { $('csFrom').value = ''; $('csTo').value = ''; }
    });
    $('csPresets').addEventListener('click', (e) => { const b = e.target.closest('[data-i]'); if (b) go(P[b.dataset.i][1], P[b.dataset.i][2]); });
    $('csRangeBtn').addEventListener('click', (e) => { e.stopPropagation(); $('csRangeMenu').hidden = !$('csRangeMenu').hidden; });
    document.addEventListener('click', (e) => { if (!e.target.closest('.cs-range')) $('csRangeMenu').hidden = true; });
    if (from && !hit) { $('csFrom').value = cpPeriod.label(from); $('csTo').value = cpPeriod.label(to || ''); }
    $('csApply').addEventListener('click', () => { let f = cpPeriod.iso($('csFrom')), t = cpPeriod.iso($('csTo')); if (f && t && f > t) [f, t] = [t, f]; go(f, t); });
})();
</script>
