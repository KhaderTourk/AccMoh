/* فلتر «الخدمات الشهرية» / «الرواتب الشهرية» وزر «أضف» (قرار 10/11/2026).
 * الفلتر بيجيب كل الحسابات اللي إلها اشتراك أو راتب شغّال من التابات الثلاثة،
 * وكل ضغطة «أضف» بتفتح نافذة فيها الشهر من قائمة جاهزة والمبلغ المقترح قابل للتعديل. */
(function () {
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
    var num = function (n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var day = function (d) { if (!d) return ''; var p = d.split('-').map(Number); return p[2] + '/' + p[1] + '/' + p[0]; };
    var ICON = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>';

    function sheet() {
        var el = document.getElementById('mpSheet');
        if (el) return el;
        el = document.createElement('div');
        el.className = 'cl-sheet';
        el.id = 'mpSheet';
        el.hidden = true;
        el.innerHTML = '<form class="cl-dialog mp-dialog" method="post" id="mpForm" novalidate role="dialog" aria-modal="true" aria-labelledby="mpTitle">'
            + '<input type="hidden" name="_token" value="' + esc((document.querySelector('meta[name=csrf-token]') || {}).content || '') + '">'
            + '<div id="mpFields"></div>'
            + '<h2 id="mpTitle"></h2><p class="mp-who" id="mpWho"></p>'
            + '<div class="cl-field"><label>الشهر</label><div class="mp-dd"><button type="button" class="mp-dd-btn" id="mpMonth"></button><div class="cl-menu mp-menu" id="mpMenu" hidden></div></div></div>'
            + '<div class="mp-calc" id="mpCalc"></div>'
            + '<div class="cl-field"><label for="mpAmt">المبلغ اللي بينضاف</label><input id="mpAmt" inputmode="decimal" dir="ltr"></div>'
            + '<div class="cl-actions"><button type="submit" class="cp-btn cp-btn-primary" id="mpSave"></button><button type="button" class="cp-btn cp-btn-ghost" id="mpCancel">إلغاء</button></div>'
            + '</form>';
        document.body.appendChild(el);
        var close = function () { el.hidden = true; };
        el.querySelector('#mpCancel').addEventListener('click', close);
        el.addEventListener('click', function (e) { if (e.target === el) close(); if (!e.target.closest('.mp-dd')) el.querySelector('#mpMenu').hidden = true; });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        el.querySelector('#mpMonth').addEventListener('click', function () { var m = el.querySelector('#mpMenu'); m.hidden = !m.hidden; });
        var amt = el.querySelector('#mpAmt');
        amt.addEventListener('input', function () { label(el); });
        el.querySelector('#mpForm').addEventListener('submit', function (e) {
            var v = parseFloat(amt.value);
            if (!(v > 0)) { e.preventDefault(); return; }
            el.querySelector('#mpSave').disabled = true;
        });
        return el;
    }
    function label(el) {
        var v = parseFloat(el.querySelector('#mpAmt').value);
        var b = el.querySelector('#mpSave');
        b.textContent = v > 0 ? 'أضف ' + num(v) + ' شيكل' : 'أضف';
        b.disabled = !(v > 0);
    }
    function pick(el, plan, i, noun) {
        var m = plan.pending[i];
        el.querySelector('#mpMonth').textContent = m.label;
        el.querySelector('#mpMenu').innerHTML = plan.pending.map(function (x, j) {
            return '<button type="button" data-i="' + j + '"' + (j === i ? ' class="on"' : '') + '>' + esc(x.label) + (j < plan.pending.length - 1 ? ' <small>فاتك</small>' : '') + '</button>';
        }).join('');
        el.querySelectorAll('#mpMenu button').forEach(function (b) {
            b.addEventListener('click', function () { el.querySelector('#mpMenu').hidden = true; pick(el, plan, +b.dataset.i, noun); });
        });
        el.querySelector('#mpCalc').innerHTML = m.days < m.inMonth
            ? '<div><span>' + esc(noun) + ' الشهري</span><b>' + num(plan.amount) + ' شيكل</b></div>'
              + '<div><span>أيام الشغل بشهر ' + esc(m.label) + '</span><b>' + m.days + ' من ' + m.inMonth + '</b></div>'
              + '<div class="t"><span>المبلغ المقترح</span><b>' + num(m.amount) + ' شيكل</b></div>'
            : '<div class="t"><span>شهر كامل</span><b>' + num(m.amount) + ' شيكل</b></div>';
        var f = el.querySelector('#mpForm');
        f.action = plan.action;
        el.querySelector('#mpFields').innerHTML = Object.keys(m.fields).map(function (k) {
            return '<input type="hidden" name="' + esc(k) + '" value="' + esc(m.fields[k]) + '">';
        }).join('');
        var amt = el.querySelector('#mpAmt');
        amt.name = m.amountField;
        amt.value = m.amount.toFixed(2);
        label(el);
    }
    function open(plan, who, noun) {
        var el = sheet();
        el.querySelector('#mpTitle').textContent = 'أضف ' + noun + ' شهر';
        el.querySelector('#mpWho').textContent = who;
        pick(el, plan, 0, noun);
        el.hidden = false;
        setTimeout(function () { el.querySelector('#mpAmt').focus(); }, 30);
    }

    /**
     * host: العنصر اللي بيحطّ فيه زر الفلتر. opts: { label, noun, rows, onChange }.
     * كل صف إله monthly: قائمة خطط فيها pending (الشهور اللي لسا ما انضافت).
     */
    function init(host, opts) {
        var on = false;
        var withPlans = function () { return opts.rows.filter(function (c) { return !c.archived && c.monthly && c.monthly.length; }); };
        function paint() {
            var n = withPlans().length;
            host.innerHTML = '<button type="button" class="mp-chip' + (on ? ' on' : '') + '" aria-pressed="' + on + '">' + ICON + esc(opts.label) + ' <b>' + n + '</b></button>';
            host.firstChild.addEventListener('click', function () { set(!on); });
        }
        function set(v) { on = !!v; paint(); opts.onChange(on); }
        paint();
        return {
            on: function () { return on; },
            set: set,
            rows: withPlans,
            /** «انضاف X من Y» لشهر هالشهر. */
            head: function (current) {
                var plans = [].concat.apply([], withPlans().map(function (c) { return c.monthly; }));
                var done = plans.filter(function (p) { return !p.pending.length; }).length;
                var pct = plans.length ? Math.round(done / plans.length * 100) : 0;
                return '<div class="mp-head"><b>شهر ' + esc(current) + '</b><span class="mp-prog"><i><i style="width:' + pct + '%"></i></i>انضاف ' + done + ' من ' + plans.length + '</span>'
                    + '<small>ولا مبلغ بينضاف لحاله</small></div>';
            },
            /** سطور الخطط تحت الصف. */
            block: function (c) {
                return '<div class="mp-plans">' + c.monthly.map(function (p, i) {
                    var late = p.pending.length > 1 ? '<em class="mp-late">فاتك ' + esc(p.pending[0].label) + '</em>' : '';
                    var stop = p.to ? '<em class="mp-stop">وقف ' + day(p.to) + '</em>' : '';
                    var btn = p.pending.length
                        ? '<button type="button" class="mp-add" data-c="' + c.id + '" data-p="' + i + '">أضف ' + esc(p.pending[0].label) + ' · ' + num(p.pending[0].amount) + '</button>'
                        : '<span class="mp-done">انضاف ✓</span>';
                    return '<div class="mp-plan"><div><b>' + esc(p.title) + '</b>' + stop + late
                        + '<span>' + num(p.amount) + ' شيكل بالشهر · من ' + day(p.from) + '</span></div>' + btn + '</div>';
                }).join('') + '</div>';
            },
            /** بيربط أزرار «أضف» بالقائمة. */
            bind: function (list, nameOf) {
                list.addEventListener('click', function (e) {
                    var b = e.target.closest('.mp-add');
                    if (!b) return;
                    e.stopPropagation();
                    var c = opts.rows.find(function (x) { return x.id === +b.dataset.c; });
                    var plan = c.monthly[+b.dataset.p];
                    open(plan, nameOf(c) + (opts.noun === 'راتب' ? '' : ' · ' + plan.title), opts.noun);
                }, true);
            },
        };
    }

    window.cpMonthly = { init: init };
})();
