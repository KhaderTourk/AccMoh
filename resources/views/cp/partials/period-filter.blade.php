{{--
    فلتر الفترة الموحّد (تسليم 1/11 §13): زر بأيقونة تقويم واسم الفترة، وقائمة فيها الفترات الجاهزة،
    وتحتها «من» و«لغاية» كحقول نص بصيغة يوم/شهر/سنة مع زر تقويم (مش حقل تاريخ المتصفح).
    الصفحة بتعبّي #csPresets وبتقرأ القيم بـ cpPeriod.iso(input) اللي بترجع YYYY-MM-DD.
--}}
<div class="cs-range">
    <button type="button" class="cs-range-btn" id="csRangeBtn"><span class="material-symbols-outlined">calendar_month</span><span id="csRangeTxt">كل الوقت</span></button>
    <div class="cl-menu cs-range-menu" id="csRangeMenu" hidden>
        <div id="csPresets"></div>
        <hr>
        <div class="cs-custom">
            @foreach (['csFrom' => 'من', 'csTo' => 'لغاية'] as $pid => $plabel)
                <label class="cs-pf-row"><span>{{ $plabel }}</span>
                    <span class="cs-date-in">
                        <input id="{{ $pid }}" inputmode="numeric" dir="ltr" placeholder="يوم/شهر/سنة" autocomplete="off">
                        <span class="cs-cal" title="اختار من التقويم"><span class="material-symbols-outlined">calendar_month</span><input type="date" tabindex="-1" data-for="{{ $pid }}" aria-label="تقويم {{ $plabel }}"></span>
                    </span>
                </label>
            @endforeach
            <button type="button" class="cp-btn cp-btn-primary" id="csApply" disabled>تطبيق</button>
        </div>
    </div>
</div>
@once
<script>
window.cpPeriod = window.cpPeriod || (function () {
    function iso(el) {
        var m = String(el && el.value || '').trim().match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
        if (!m) return '';
        var d = +m[1], mo = +m[2], y = +m[3];
        var dt = new Date(y, mo - 1, d);
        if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return '';
        return y + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }
    function label(isoDay) { if (!isoDay) return ''; var p = isoDay.split('-').map(Number); return p[2] + '/' + p[1] + '/' + p[0]; }
    function wire() {
        var from = document.getElementById('csFrom'), to = document.getElementById('csTo'), apply = document.getElementById('csApply');
        if (!from || from.dataset.wired) return; from.dataset.wired = '1';
        var check = function () { apply.disabled = !(iso(from) || iso(to)); };
        [from, to].forEach(function (el) { el.addEventListener('input', check); });
        document.querySelectorAll('#csRangeMenu input[type=date][data-for]').forEach(function (p) {
            p.addEventListener('change', function () { if (p.value) { document.getElementById(p.dataset.for).value = label(p.value); check(); } });
        });
        check();
    }
    document.addEventListener('DOMContentLoaded', wire);
    return { iso: iso, label: label, wire: wire };
})();
</script>
@endonce
