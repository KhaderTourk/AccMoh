/*
 * الفلاتر بدون إعادة تحميل (12/11/2026، طلب م. محمد): أي رابط أو بحث بيغيّر فلاتر نفس الصفحة
 * (العملة، الفترة، النوع، الدرج، الصفحات) بيجيب الصفحة بالخلفية وبيبدّل بس الأجزاء المعلّمة data-soft="اسم".
 * الصفحة بتضل مكانها (نفس السكرول)، والرابط بيتحدّث، فالتحديث وزر الرجوع بيضلّوا على نفس الفلتر.
 * صفحة ما فيها data-soft: بيصير تحميل عادي. أي خطأ: تحميل عادي كمان.
 */
(function () {
    'use strict';
    var busy = null;
    var has = function () { return !!document.querySelector('[data-soft]'); };
    var same = function (u) { return u.origin === location.origin && u.pathname === location.pathname; };
    var map = function (root) {
        var m = {};
        Array.prototype.forEach.call(root.querySelectorAll('[data-soft]'), function (el) { m[el.getAttribute('data-soft')] = el; });
        return m;
    };

    function go(url, opts) {
        opts = opts || {};
        var u = new URL(url, location.href);
        if (!same(u) || !has()) { location.href = u.href; return; }
        if (busy) busy.abort();
        var ac = window.AbortController ? new AbortController() : null;
        busy = ac;
        document.documentElement.classList.add('cp-soft-busy');
        fetch(u.href, { credentials: 'same-origin', headers: { 'X-Cp-Soft': '1', Accept: 'text/html' }, signal: ac ? ac.signal : undefined })
            .then(function (r) {
                if (!r.ok || (r.redirected && new URL(r.url).pathname !== u.pathname)) throw new Error('hard');
                return r.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var next = map(doc), cur = map(document);
                if (!Object.keys(next).length) throw new Error('hard');
                Object.keys(cur).forEach(function (k) {
                    if (next[k]) cur[k].replaceWith(document.importNode(next[k], true));
                });
                if (opts.push !== false) history.pushState({ cpSoft: 1 }, '', u.href);
                // حقول البحث برا الأجزاء المبدّلة بتاخد قيمها من الرابط الجديد (مهم لزر الرجوع).
                Array.prototype.forEach.call(document.querySelectorAll('.cp-main form input[name]'), function (el) {
                    var f = el.form; if (!f || (f.getAttribute('method') || 'get').toLowerCase() !== 'get' || el.closest('[data-soft]')) return;
                    if (el.type === 'search' || el.type === 'text' || el.type === 'hidden') el.value = u.searchParams.get(el.name) || '';
                });
                if (doc.title) document.title = doc.title;
                document.dispatchEvent(new CustomEvent('cp:soft', { detail: { url: u.href } }));
            })
            .catch(function (e) {
                if (e && e.name === 'AbortError') return;
                location.href = u.href;
            })
            .then(function () {
                if (busy === ac) { busy = null; document.documentElement.classList.remove('cp-soft-busy'); }
            });
    }
    window.cpSoft = { go: go, active: has };

    // روابط الفلاتر: نفس الصفحة، رابط مختلف بس بالـ query.
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.target || a.hasAttribute('download') || a.hasAttribute('data-hard') || !a.closest('.cp-main')) return;
        var u = new URL(a.href, location.href);
        if (!same(u) || !has()) return;
        if (u.search === location.search && u.hash) return; // رابط لمكان بنفس الصفحة
        e.preventDefault();
        var m = a.closest('.cl-menu'); if (m) m.hidden = true;
        go(u.href);
    });

    // البحث (form GET لنفس الصفحة): الرابط الحالي هو مصدر الفلاتر، والحقول الظاهرة بتغيّر عليه.
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (e.defaultPrevented || !f.closest || !f.closest('.cp-main') || (f.getAttribute('method') || 'get').toLowerCase() !== 'get' || f.hasAttribute('data-hard')) return;
        var u = new URL(f.getAttribute('action') || location.href, location.href);
        if (!same(u) || !has()) return;
        e.preventDefault();
        var next = new URL(location.href);
        next.hash = '';
        next.searchParams.delete('page');
        Array.prototype.forEach.call(f.elements, function (el) {
            if (!el.name || el.type === 'hidden' || el.disabled) return;
            if (el.value.trim()) next.searchParams.set(el.name, el.value.trim()); else next.searchParams.delete(el.name);
        });
        go(next.href);
    });

    // زر الرجوع/التقدّم بين فلاتر نفس الصفحة.
    var last = location.pathname + location.search;
    try { history.replaceState(Object.assign({}, history.state, { cpSoft: 1 }), ''); } catch (_) {}
    window.addEventListener('popstate', function () {
        var now = location.pathname + location.search;
        if (now === last) return;
        last = now;
        if (has()) go(location.href, { push: false }); else location.reload();
    });
    document.addEventListener('cp:soft', function () { last = location.pathname + location.search; });
})();
