/*
 * قوائم الاختيار بستايل براند (12/11/2026، قاعدة «بدون قوائم المتصفح»).
 * كل <select> عادي بالنظام بيصير زر + قائمة، والـ select الأصلي بيضل تحت (مخفي)
 * عشان الفورم والتحقق والـ JS الموجود يضلوا شغالين. التغيير بيطلق input وchange عادي.
 * استثناء: multiple، size > 1، أو data-native.
 */
(function () {
    'use strict';
    var desc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    var idxDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
    // لما الكود يغيّر القيمة مباشرة (select.value = x) الزر بيتحدّث كمان.
    Object.defineProperty(HTMLSelectElement.prototype, 'value', {
        configurable: true, enumerable: desc.enumerable,
        get: function () { return desc.get.call(this); },
        set: function (v) { desc.set.call(this, v); if (this.__cps) this.__cps.sync(); }
    });
    Object.defineProperty(HTMLSelectElement.prototype, 'selectedIndex', {
        configurable: true, enumerable: idxDesc.enumerable,
        get: function () { return idxDesc.get.call(this); },
        set: function (v) { idxDesc.set.call(this, v); if (this.__cps) this.__cps.sync(); }
    });

    var open = null;
    var chev = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';

    function close() {
        if (!open) return;
        open.menu.hidden = true;
        open.btn.setAttribute('aria-expanded', 'false');
        open = null;
    }

    function enhance(sel) {
        if (sel.__cps || sel.multiple || sel.size > 1 || sel.hasAttribute('data-native') || !sel.parentNode) return;
        var wrap = document.createElement('span');
        wrap.className = 'cp-sel';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cp-sel-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        var lab = sel.id ? document.querySelector('label[for="' + sel.id + '"]') : null;
        if (lab) btn.setAttribute('aria-label', lab.textContent.trim());
        btn.innerHTML = '<span class="cp-sel-txt"></span>' + chev;
        var menu = document.createElement('div');
        menu.className = 'cp-sel-menu';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        wrap.appendChild(btn);
        document.body.appendChild(menu);
        sel.tabIndex = -1;
        sel.classList.add('cp-sel-native');
        if (lab) lab.addEventListener('click', function (e) { e.preventDefault(); btn.focus(); });

        function sync() {
            var o = sel.options[idxDesc.get.call(sel)];
            btn.querySelector('.cp-sel-txt').textContent = o ? o.text : '';
            btn.classList.toggle('ph', !o || o.value === '');
            btn.disabled = sel.disabled;
        }
        function build() {
            var html = '', cur = desc.get.call(sel);
            Array.prototype.forEach.call(sel.children, function (n) {
                if (n.tagName === 'OPTGROUP') {
                    html += '<b class="cp-sel-h">' + esc(n.label) + '</b>';
                    Array.prototype.forEach.call(n.children, function (o) { html += opt(o, cur); });
                } else if (n.tagName === 'OPTION') { html += opt(n, cur); }
            });
            menu.innerHTML = html || '<p class="cp-sel-empty">ما في خيارات.</p>';
        }
        function opt(o, cur) {
            if (o.hidden) return '';
            var on = o.value === cur && o.selected;
            return '<button type="button" role="option" data-v="' + esc(o.value) + '" aria-selected="' + on + '"' + (o.disabled ? ' disabled' : '') + ' class="' + (on ? 'on' : '') + '">' + esc(o.text) + '</button>';
        }
        function place() {
            var r = btn.getBoundingClientRect(), vh = window.innerHeight, vw = window.innerWidth;
            if (vw <= 600) { menu.classList.add('sheet'); menu.style.cssText = ''; return; }
            menu.classList.remove('sheet');
            var below = vh - r.bottom - 12, above = r.top - 12;
            var up = below < 220 && above > below;
            var maxH = Math.max(160, Math.min(320, up ? above : below));
            menu.style.cssText = 'position:fixed;min-width:' + r.width + 'px;max-height:' + maxH + 'px;right:' + Math.max(8, vw - r.right) + 'px;'
                + (up ? 'bottom:' + (vh - r.top + 6) + 'px' : 'top:' + (r.bottom + 6) + 'px');
        }
        function show() {
            if (btn.disabled) return;
            if (open && open.sel === sel) { close(); return; }
            close();
            build(); place();
            menu.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            open = { sel: sel, btn: btn, menu: menu };
            var on = menu.querySelector('.on') || menu.querySelector('button:not([disabled])');
            if (on) { on.scrollIntoView({ block: 'nearest' }); }
        }
        function pick(v) {
            if (desc.get.call(sel) !== v) {
                desc.set.call(sel, v);
                sync();
                sel.dispatchEvent(new Event('input', { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
            close();
            btn.focus();
        }
        btn.addEventListener('click', function (e) { e.stopPropagation(); show(); });
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!open || open.sel !== sel) show();
                var b = menu.querySelector('.on') || menu.querySelector('button:not([disabled])');
                if (b) b.focus();
            }
        });
        menu.addEventListener('click', function (e) {
            e.stopPropagation();
            var b = e.target.closest('[data-v]');
            if (b && !b.disabled) pick(b.getAttribute('data-v'));
        });
        menu.addEventListener('keydown', function (e) {
            var items = Array.prototype.slice.call(menu.querySelectorAll('button:not([disabled])'));
            var i = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') { e.preventDefault(); (items[i + 1] || items[0]).focus(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); (items[i - 1] || items[items.length - 1]).focus(); }
            else if (e.key === 'Escape' || e.key === 'Tab') { e.preventDefault(); close(); btn.focus(); }
        });
        sel.addEventListener('change', sync);
        sel.addEventListener('invalid', function () { wrap.classList.add('bad'); });
        sel.addEventListener('change', function () { wrap.classList.remove('bad'); });
        new MutationObserver(function () { sync(); if (open && open.sel === sel) build(); })
            .observe(sel, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'selected'] });
        sel.__cps = { sync: sync, menu: menu };
        sync();
    }

    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    function fit(m) {
        m.style.translate = ''; m.style.maxWidth = 'calc(100vw - 16px)';
        // الحدود: اللوحة الرئيسية (عشان ما تنزل تحت القائمة الجانبية) وإلا الشاشة.
        var r = m.getBoundingClientRect(), vw = document.documentElement.clientWidth, dx = 0;
        if (!r.width) return;
        var box = m.closest('.cp-main'), b = box ? box.getBoundingClientRect() : null;
        var lo = Math.max(8, b ? b.left + 8 : 8), hi = Math.min(vw - 8, b ? b.right - 8 : vw - 8);
        if (r.right > hi) dx = hi - r.right;
        if (r.left + dx < lo) dx = lo - r.left;
        if (dx) m.style.translate = Math.round(dx) + 'px 0';
    }
    window.cpFitMenu = fit;

    function scan(root) {
        if (root.tagName === 'SELECT') { enhance(root); return; }
        if (root.querySelectorAll) Array.prototype.forEach.call(root.querySelectorAll('select'), enhance);
    }
    // لما ينشال select من الصفحة (نافذة بتنبني من جديد) قائمته كمان بتنشال.
    function sweep() {
        Array.prototype.forEach.call(document.querySelectorAll('body > .cp-sel-menu'), function (m) {
            var alive = Array.prototype.some.call(document.querySelectorAll('select'), function (s) { return s.__cps && s.__cps.menu === m; });
            if (!alive) { if (open && open.menu === m) open = null; m.remove(); }
        });
    }
    function start() {
        scan(document.body);
        new MutationObserver(function (list) {
            var removed = false;
            list.forEach(function (m) {
                m.addedNodes.forEach(function (n) { if (n.nodeType === 1) scan(n); });
                if (m.removedNodes.length) removed = true;
            });
            if (removed) sweep();
        }).observe(document.body, { childList: true, subtree: true });
        // أي قائمة .cl-menu بتنفتح بتضل جوا الشاشة (12/11/2026: قوائم + خدمة / + دفعة كانت تطلع برا الطرف).
        new MutationObserver(function (list) {
            list.forEach(function (m) { if (m.target.classList && m.target.classList.contains('cl-menu') && !m.target.hidden) fit(m.target); });
        }).observe(document.body, { attributes: true, attributeFilter: ['hidden'], subtree: true });
        window.addEventListener('resize', function () { Array.prototype.forEach.call(document.querySelectorAll('.cl-menu:not([hidden])'), fit); });
        document.addEventListener('click', close);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) { e.stopPropagation(); var b = open.btn; close(); b.focus(); } }, true);
        window.addEventListener('resize', close);
        document.addEventListener('scroll', function (e) { if (open && !open.menu.contains(e.target)) close(); }, true);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
