/* لوحة التحكم — تحسينات الواجهة (بدون أي منطق مالي)
   نافذة تأكيد، منع الإرسال المزدوج، شريط تقدم، تموج الأزرار، بحث سريع Ctrl+K. */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    function el(tag, cls, html) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        if (html != null) node.innerHTML = html;
        return node;
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    /* ——— شريط التقدم ——— */
    var progress = null;
    function startProgress() {
        if (!progress) return;
        progress.classList.remove('is-active');
        void progress.offsetWidth;
        progress.classList.add('is-active');
    }
    function stopProgress() {
        if (progress) progress.classList.remove('is-active');
    }

    /* ——— نافذة التأكيد ——— */
    var dialog = null;
    var dialogResolve = null;
    var lastFocus = null;

    function buildDialog() {
        dialog = el('div', 'cp-dialog');
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'cp-dialog-title');
        dialog.innerHTML =
            '<div class="cp-dialog-panel">' +
                '<div class="cp-dialog-icon"><span class="material-symbols-outlined">warning</span></div>' +
                '<div class="cp-dialog-title" id="cp-dialog-title">تأكيد العملية</div>' +
                '<div class="cp-dialog-text"></div>' +
                '<div class="cp-dialog-actions">' +
                    '<button type="button" class="cp-btn cp-btn-solid-danger" data-ok>تأكيد</button>' +
                    '<button type="button" class="cp-btn cp-btn-ghost" data-cancel>إلغاء</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(dialog);
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog || e.target.closest('[data-cancel]')) closeDialog(false);
            else if (e.target.closest('[data-ok]')) closeDialog(true);
        });
        dialog.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); closeDialog(false); }
            if (e.key === 'Tab') {
                var buttons = dialog.querySelectorAll('button');
                var first = buttons[0], last = buttons[buttons.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });
    }

    function askConfirm(message) {
        return new Promise(function (resolve) {
            if (!dialog) buildDialog();
            dialog.querySelector('.cp-dialog-text').textContent = message;
            lastFocus = document.activeElement;
            dialogResolve = resolve;
            dialog.classList.add('is-open');
            setTimeout(function () { dialog.querySelector('[data-ok]').focus(); }, 30);
        });
    }

    function closeDialog(result) {
        if (!dialog) return;
        dialog.classList.remove('is-open');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
        var resolve = dialogResolve;
        dialogResolve = null;
        if (resolve) resolve(result);
    }

    // onsubmit="return confirm('...')" يتحول إلى data-confirm حتى تظهر النافذة المصممة بنفس النص.
    var CONFIRM_RE = /^\s*return\s+confirm\(\s*(['"])([\s\S]*)\1\s*\)\s*;?\s*$/;
    function upgradeConfirms(root) {
        (root || document).querySelectorAll('form[onsubmit]').forEach(function (form) {
            var m = (form.getAttribute('onsubmit') || '').match(CONFIRM_RE);
            if (!m) return;
            form.setAttribute('data-confirm', m[2].replace(/\\(['"\\])/g, '$1'));
            form.removeAttribute('onsubmit');
            form.onsubmit = null;
        });
    }

    /* ——— الإرسال: تأكيد ثم قفل الزر ——— */
    function lockForm(form, submitter) {
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
        if (form.target && form.target !== '_self') return;
        if (form.hasAttribute('data-no-lock')) return;
        form.setAttribute('data-submitting', '1');
        var btn = submitter || form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
        if (btn) btn.classList.add('cp-is-loading');
        // أمان: إن لم تنتقل الصفحة (تنزيل ملف مثلاً) نعيد الزر بعد قليل
        setTimeout(function () { unlockForm(form); }, 8000);
    }

    function unlockForm(form) {
        form.removeAttribute('data-submitting');
        form.querySelectorAll('.cp-is-loading').forEach(function (b) { b.classList.remove('cp-is-loading'); });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (form.hasAttribute('data-submitting')) {
            e.preventDefault();
            return;
        }

        var message = form.getAttribute('data-confirm');
        if (message && !form.__cpConfirmed) {
            e.preventDefault();
            var submitter = e.submitter || null;
            askConfirm(message).then(function (ok) {
                if (!ok) return;
                form.__cpConfirmed = true;
                if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
                else form.submit();
                form.__cpConfirmed = false;
            });
            return;
        }

        // يعمل بعد معالجات الصفحة نفسها؛ إن أوقفت الإرسال لا نقفل شيئاً
        setTimeout(function () {
            if (e.defaultPrevented) return;
            lockForm(form, e.submitter);
            startProgress();
        }, 0);
    });

    /* ——— روابط التنقل ——— */
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download')) return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
        var url;
        try { url = new URL(a.href, location.href); } catch (err) { return; }
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        if (/\/(export|pdf|print)/i.test(url.pathname)) return;
        startProgress();
    });

    // عند الرجوع بزر المتصفح تُستعاد الصفحة من الذاكرة: نعيد كل شيء لحالته
    window.addEventListener('pageshow', function () {
        stopProgress();
        document.querySelectorAll('form[data-submitting]').forEach(unlockForm);
    });

    /* ——— تموج الأزرار ——— */
    document.addEventListener('pointerdown', function (e) {
        if (reduceMotion) return;
        var btn = e.target.closest && e.target.closest('.cp-btn, .cp-main button.bg-primary, .cp-main a.bg-primary');
        if (!btn) return;
        var rect = btn.getBoundingClientRect();
        var size = Math.max(rect.width, rect.height);
        var ripple = el('span', 'cp-ripple');
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        if (getComputedStyle(btn).position === 'static') btn.style.position = 'relative';
        btn.style.overflow = 'hidden';
        btn.appendChild(ripple);
        setTimeout(function () { ripple.remove(); }, 650);
    });

    /* ——— البحث السريع Ctrl+K ——— */
    var palette = null, paletteInput = null, paletteList = null, paletteItems = [], activeIndex = 0;

    function collectCommands() {
        var commands = [];
        var seen = {};
        var actions = document.getElementById('cp-quick-actions');
        if (actions) {
            try {
                JSON.parse(actions.textContent || '[]').forEach(function (c) {
                    c.group = 'إجراءات سريعة';
                    commands.push(c);
                    seen[c.url] = true;
                });
            } catch (e) {}
        }
        document.querySelectorAll('#cp-sidebar a.cp-nav-item').forEach(function (a) {
            if (seen[a.href]) return;
            seen[a.href] = true;
            var icon = a.querySelector('.material-symbols-outlined');
            var label = Array.prototype.filter.call(a.querySelectorAll('span'), function (s) {
                return !s.classList.contains('material-symbols-outlined') && !/^\d+$/.test(s.textContent.trim());
            })[0];
            var section = a.closest('.cp-nav-section');
            var groupLabel = section ? section.querySelector('.cp-section-toggle span') : null;
            commands.push({
                label: label ? label.textContent.trim() : a.textContent.trim(),
                icon: icon ? icon.textContent.trim() : 'arrow_back',
                url: a.href,
                group: groupLabel ? groupLabel.textContent.trim() : 'الصفحات'
            });
        });
        return commands;
    }

    function normalize(text) {
        return String(text || '').toLowerCase()
            .replace(/[\u064B-\u0652\u0640]/g, '')
            .replace(/[أإآ]/g, 'ا')
            .replace(/ى/g, 'ي')
            .replace(/ة/g, 'ه');
    }

    function buildPalette() {
        palette = el('div', 'cp-dialog cp-palette');
        palette.setAttribute('role', 'dialog');
        palette.setAttribute('aria-modal', 'true');
        palette.setAttribute('aria-label', 'بحث سريع');
        palette.innerHTML =
            '<div class="cp-dialog-panel">' +
                '<div class="cp-palette-input-wrap">' +
                    '<span class="material-symbols-outlined">search</span>' +
                    '<input type="text" class="cp-palette-input" placeholder="ابحث عن صفحة أو عملية..." autocomplete="off" aria-label="بحث">' +
                    '<span class="cp-kbd">Esc</span>' +
                '</div>' +
                '<div class="cp-palette-list" role="listbox"></div>' +
                '<div class="cp-palette-foot"><span><span class="cp-kbd">↑</span> <span class="cp-kbd">↓</span> للتنقل</span><span><span class="cp-kbd">Enter</span> للفتح</span></div>' +
            '</div>';
        document.body.appendChild(palette);
        paletteInput = palette.querySelector('.cp-palette-input');
        paletteList = palette.querySelector('.cp-palette-list');

        palette.addEventListener('click', function (e) {
            if (e.target === palette) closePalette();
            var item = e.target.closest('.cp-palette-item');
            if (item) go(item.getAttribute('data-url'));
        });
        palette.addEventListener('mousemove', function (e) {
            var item = e.target.closest('.cp-palette-item');
            if (item) setActive(+item.getAttribute('data-index'));
        });
        paletteInput.addEventListener('input', renderPalette);
        paletteInput.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                var item = paletteList.querySelector('.cp-palette-item.is-active');
                if (item) go(item.getAttribute('data-url'));
            } else if (e.key === 'Escape') { e.preventDefault(); closePalette(); }
        });
    }

    function renderPalette() {
        var q = normalize(paletteInput.value.trim());
        var all = collectCommands();
        paletteItems = q ? all.filter(function (c) { return normalize(c.label + ' ' + c.group).indexOf(q) !== -1; }) : all;
        if (!paletteItems.length) {
            paletteList.innerHTML = '<div class="cp-palette-empty">لا توجد نتائج مطابقة</div>';
            return;
        }
        var html = '', lastGroup = null;
        paletteItems.forEach(function (c, i) {
            if (c.group !== lastGroup) {
                html += '<div class="cp-palette-group">' + escapeHtml(c.group) + '</div>';
                lastGroup = c.group;
            }
            html += '<div class="cp-palette-item" role="option" data-index="' + i + '" data-url="' + escapeHtml(c.url) + '">' +
                '<span class="material-symbols-outlined">' + escapeHtml(c.icon || 'arrow_back') + '</span>' +
                '<span>' + escapeHtml(c.label) + '</span>' +
                '<span class="material-symbols-outlined cp-palette-go">keyboard_backspace</span>' +
            '</div>';
        });
        paletteList.innerHTML = html;
        setActive(0);
    }

    function setActive(i) {
        var items = paletteList.querySelectorAll('.cp-palette-item');
        if (!items.length) return;
        activeIndex = (i + items.length) % items.length;
        items.forEach(function (n, idx) {
            n.classList.toggle('is-active', idx === activeIndex);
            n.setAttribute('aria-selected', idx === activeIndex ? 'true' : 'false');
        });
        items[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function go(url) {
        if (!url) return;
        closePalette();
        startProgress();
        location.href = url;
    }

    function openPalette() {
        if (!palette) buildPalette();
        lastFocus = document.activeElement;
        paletteInput.value = '';
        renderPalette();
        palette.classList.add('is-open');
        setTimeout(function () { paletteInput.focus(); }, 30);
    }

    function closePalette() {
        if (!palette) return;
        palette.classList.remove('is-open');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    window.cpOpenPalette = openPalette;
    window.cpConfirm = askConfirm;

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K' || e.code === 'KeyK')) {
            e.preventDefault();
            if (palette && palette.classList.contains('is-open')) closePalette();
            else openPalette();
        }
    });

    /* ——— عدّاد الأرقام: يعد من الصفر للقيمة ثم يعيد النص الأصلي كما هو ——— */
    var NUM_RE = /-?\d[\d,]*(?:\.\d+)?/;
    function countUp(node) {
        var original = node.textContent;
        var m = original.match(NUM_RE);
        if (!m) return;
        var raw = m[0];
        var target = parseFloat(raw.replace(/,/g, ''));
        if (!isFinite(target) || target === 0) return;
        var decimals = (raw.split('.')[1] || '').length;
        var grouped = raw.indexOf(',') !== -1;
        var before = original.slice(0, m.index), after = original.slice(m.index + raw.length);
        var duration = 1100, start = null;
        function fmt(v) {
            var str = Math.abs(v).toFixed(decimals);
            if (grouped) {
                var parts = str.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                str = parts.join('.');
            }
            return (v < 0 ? '-' : '') + str;
        }
        function frame(t) {
            if (start === null) start = t;
            var p = Math.min((t - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 4);
            node.textContent = before + fmt(target * eased) + after;
            if (p < 1) requestAnimationFrame(frame);
            else node.textContent = original;
        }
        requestAnimationFrame(frame);
    }

    function initCountUps() {
        var nodes = document.querySelectorAll('[data-countup]');
        if (!nodes.length || reduceMotion) return;
        if (!('IntersectionObserver' in window)) { nodes.forEach(countUp); return; }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                io.unobserve(entry.target);
                countUp(entry.target);
            });
        }, { threshold: 0.4 });
        nodes.forEach(function (n) { io.observe(n); });
    }

    /* ——— التشغيل ——— */
    ready(function () {
        progress = el('div', 'cp-progress');
        progress.setAttribute('aria-hidden', 'true');
        document.body.appendChild(progress);

        upgradeConfirms(document);
        initCountUps();

        document.querySelectorAll('[data-cp-palette]').forEach(function (btn) {
            btn.addEventListener('click', openPalette);
        });

        // ظل الرأس عند التمرير
        var header = document.querySelector('.cp-header');
        if (header) {
            var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 4); };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }
    });
})();
