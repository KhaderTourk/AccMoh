(function () {
    function headerLabel(th) {
        return (th.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function isSortableHeader(th) {
        if (th.getAttribute('data-sort') === 'off' || th.colSpan > 1) {
            return false;
        }
        var label = headerLabel(th);
        return label !== '' && label !== 'إجراء' && label !== 'إجراءات';
    }

    function cellText(row, index) {
        var cell = row.cells[index];
        return cell ? (cell.innerText || '').replace(/\s+/g, ' ').trim() : '';
    }

    function valueOf(text) {
        var raw = (text || '').replace(/\s+/g, ' ').trim();
        if (!raw || raw === '—' || raw === '-') {
            return { empty: true, num: null, text: '' };
        }

        var date = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2}))?$/);
        if (date) {
            return {
                empty: false,
                num: Date.UTC(+date[3], +date[2] - 1, +date[1], +(date[4] || 0), +(date[5] || 0)),
                text: raw
            };
        }

        var time = raw.match(/^(\d{1,2}):(\d{2})$/);
        if (time) {
            return { empty: false, num: (+time[1] * 60) + (+time[2]), text: raw };
        }

        var withoutKnown = raw.replace(/عربون|مقدماً|مقدم|شيكل|دولار|دينار/g, '');
        var letters = /[A-Za-z\u0600-\u06FF]{2,}/.test(withoutKnown);
        var match = raw.replace(/,/g, '').match(/-?\d+(?:\.\d+)?/);
        if (match && !letters) {
            var num = parseFloat(match[0]);
            if (raw.indexOf('عربون') !== -1 || raw.indexOf('مقدماً') !== -1) {
                num = -Math.abs(num);
            }
            return { empty: false, num: num, text: raw };
        }

        return { empty: false, num: null, text: raw };
    }

    function compare(a, b, direction) {
        if (a.empty && b.empty) return 0;
        if (a.empty) return 1;
        if (b.empty) return -1;

        var result;
        if (a.num !== null && b.num !== null) {
            result = a.num - b.num;
        } else {
            result = a.text.localeCompare(b.text, 'ar', { numeric: true, sensitivity: 'base' });
        }

        return direction === 'desc' ? -result : result;
    }

    function isLocked(row) {
        if (row.hasAttribute('data-sort-lock') || row.classList.contains('total')) {
            return true;
        }
        var first = row.cells[0] ? (row.cells[0].innerText || '').replace(/\s+/g, ' ').trim() : '';
        return first === 'الإجمالي';
    }

    function sortBody(tbody, index, direction) {
        var rows = Array.prototype.slice.call(tbody.rows);
        var locked = [];
        var sortable = [];
        var placeholders = [];

        rows.forEach(function (row) {
            if (isLocked(row)) {
                locked.push(row);
            } else if (row.cells.length <= 1) {
                placeholders.push(row);
            } else {
                sortable.push(row);
            }
        });

        sortable.sort(function (left, right) {
            return compare(valueOf(cellText(left, index)), valueOf(cellText(right, index)), direction);
        });

        sortable.concat(locked, placeholders).forEach(function (row) {
            tbody.appendChild(row);
        });
    }

    function bind(table) {
        if (table.getAttribute('data-sortable') === 'off' || !table.tHead || !table.tHead.rows.length) {
            return;
        }

        var headRow = table.tHead.rows[table.tHead.rows.length - 1];
        var headers = Array.prototype.slice.call(headRow.cells);

        headers.forEach(function (th, index) {
            if (!isSortableHeader(th)) {
                return;
            }

            th.classList.add('cp-sortable');
            th.setAttribute('tabindex', '0');
            th.setAttribute('role', 'button');
            th.setAttribute('title', 'ترتيب تصاعدي أو تنازلي');

            function activate() {
                var direction = th.classList.contains('cp-sort-asc') ? 'desc' : 'asc';
                headers.forEach(function (header) {
                    header.classList.remove('cp-sort-asc', 'cp-sort-desc');
                    header.removeAttribute('aria-sort');
                });
                th.classList.add(direction === 'asc' ? 'cp-sort-asc' : 'cp-sort-desc');
                th.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
                Array.prototype.forEach.call(table.tBodies, function (tbody) {
                    sortBody(tbody, index, direction);
                });
            }

            th.addEventListener('click', activate);
            th.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activate();
                }
            });
        });
    }

    document.querySelectorAll('.cp-main table').forEach(bind);
})();
