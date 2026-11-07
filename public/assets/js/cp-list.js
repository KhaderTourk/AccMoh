/* قوائم الأطراف (الزبائن، الموظفين، الموردين، الأشخاص): الترتيب، التثبيت ★، سطر «آخر دفعة»، وسطر الملخص.
   الرصيد بالصفوف: موجب = «عليه» (وردي)، سالب = «إله» (أخضر). */
(function () {
    'use strict';
    var SORTS = [
        ['collect', 'الأولى بالتحصيل'],
        ['oldest', 'أقدم دفعة'],
        ['highest', 'الأعلى رصيد'],
        ['recent', 'آخر حركة'],
        ['pinned', 'المثبّتين أول'],
        ['name', 'الاسم'],
    ];
    var fmt = function (n) { return Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var DAY = 86400000;

    function days(from, today) {
        if (!from) return null;
        return Math.max(0, Math.round((new Date(today + 'T00:00:00') - new Date(from + 'T00:00:00')) / DAY));
    }
    /** أكبر رصيد «عليه» للصف، بالشيكل إذا موجود */
    function owed(c) {
        var ils = c.balances.find(function (b) { return b.code === 'ILS'; });
        if (ils) return ils.amount;
        return c.balances.reduce(function (a, b) { return Math.max(a, b.amount); }, 0);
    }
    function absBal(c) { return c.balances.reduce(function (a, b) { return Math.max(a, Math.abs(b.amount)); }, 0); }
    function isDue(c) { return c.balances.some(function (b) { return b.amount > 0; }); }
    /** الأيام من آخر دفعة، أو من أول حركة إذا ما دفع إشي */
    function idle(c, today) { return days(c.last_pay || c.first_move, today); }

    function sort(list, mode, nameOf, today) {
        var byName = function (a, b) { return nameOf(a).localeCompare(nameOf(b), 'ar'); };
        var cmp = {
            collect: function (a, b) {
                var sa = isDue(a) ? owed(a) * Math.max(1, idle(a, today) || 0) : -1;
                var sb = isDue(b) ? owed(b) * Math.max(1, idle(b, today) || 0) : -1;
                return sb - sa || byName(a, b);
            },
            oldest: function (a, b) {
                var da = isDue(a) ? (idle(a, today) || 0) : -1, db = isDue(b) ? (idle(b, today) || 0) : -1;
                return db - da || byName(a, b);
            },
            highest: function (a, b) { return absBal(b) - absBal(a) || byName(a, b); },
            recent: function (a, b) { return String(b.last_move || '').localeCompare(String(a.last_move || '')) || byName(a, b); },
            pinned: function (a, b) { return (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0) || byName(a, b); },
            name: byName,
        }[mode] || byName;
        return list.slice().sort(cmp);
    }

    /** سطر «آخر دفعة قبل N يوم» تحت اسم اللي عليه رصيد */
    function ageLine(c, today) {
        if (!isDue(c)) return '';
        var n = idle(c, today);
        if (n === null) return '';
        var txt = c.last_pay ? (n === 0 ? 'آخر دفعة اليوم' : 'آخر دفعة قبل ' + n + ' يوم') : 'ما دفع إشي من ' + n + ' يوم';
        return '<small class="cl-age' + (n > 30 ? ' late' : '') + '">' + txt + '</small>';
    }

    /** «N شخص · عليه … · إله …» */
    function summary(list, noun, symbols) {
        var due = {}, cred = {};
        list.forEach(function (c) {
            c.balances.forEach(function (b) {
                var t = b.amount > 0 ? due : cred;
                t[b.code] = (t[b.code] || 0) + Math.abs(b.amount);
            });
        });
        var part = function (t) { return Object.keys(t).map(function (k) { return fmt(t[k]) + ' ' + ((symbols || {})[k] || k); }).join(' · '); };
        var out = '<b>' + list.length + '</b> ' + noun;
        if (Object.keys(due).length) out += ' · عليه <b class="due">' + part(due) + '</b>';
        if (Object.keys(cred).length) out += ' · إله <b class="credit">' + part(cred) + '</b>';
        return out;
    }

    /** زر «ترتيب» بقائمة بثيم النظام، وآخر اختيار بينحفظ لكل شاشة */
    function sortMenu(host, key, onChange) {
        var store = 'cpSort:' + key, mode = 'collect';
        try { mode = localStorage.getItem(store) || mode; } catch (e) {}
        if (!SORTS.some(function (s) { return s[0] === mode; })) mode = 'collect';
        host.classList.add('cl-sort');
        host.innerHTML = '<button type="button" class="cl-sort-btn" aria-haspopup="true"><span class="material-symbols-outlined">swap_vert</span><span class="cl-sort-txt"></span></button>'
            + '<div class="cl-menu cl-sort-menu" hidden>' + SORTS.map(function (s) { return '<button type="button" data-s="' + s[0] + '">' + s[1] + '</button>'; }).join('') + '</div>';
        var btn = host.querySelector('.cl-sort-btn'), menu = host.querySelector('.cl-sort-menu');
        function paint() {
            host.querySelector('.cl-sort-txt').textContent = SORTS.find(function (s) { return s[0] === mode; })[1];
            menu.querySelectorAll('[data-s]').forEach(function (b) { b.classList.toggle('on', b.dataset.s === mode); });
        }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.hidden;
            document.querySelectorAll('.cl-menu').forEach(function (m) { m.hidden = true; });
            menu.hidden = !open;
        });
        menu.addEventListener('click', function (e) {
            var b = e.target.closest('[data-s]'); if (!b) return;
            mode = b.dataset.s; menu.hidden = true;
            try { localStorage.setItem(store, mode); } catch (e2) {}
            paint(); onChange(mode);
        });
        paint();
        return { get: function () { return mode; } };
    }

    function pin(c) {
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        return fetch(c.pin, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (j) { c.pinned = !!j.pinned; return c.pinned; });
    }

    window.cpList = { sort: sort, ageLine: ageLine, summary: summary, sortMenu: sortMenu, pin: pin };
})();
