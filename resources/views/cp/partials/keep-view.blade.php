{{--
    «ضل مكانك»: بعد أي حركة (حفظ، حذف، أرشفة...) الشاشة بترجع بنفس التاب والفلتر والفترة ومكان التمرير.
    كل شاشة بتسجّل حالتها بـ cpKeep.track(() => ({...}))، وبتقرأها عند الفتح بـ cpKeep.take().
    الحالة بتنحفظ بالمتصفح لحظة ما تطلع من الصفحة، وبترجع بس لما ترجع لنفس الصفحة بعد حركة (في رسالة تأكيد أو خطأ)،
    يعني الدخول العادي من القائمة بيفتح الشاشة على وضعها الأساسي.
--}}
<script>
(function () {
    var KEY = 'cpKeep:' + location.pathname;
    var afterAction = @json(session()->has('success') || session()->has('error') || ($errors ?? null)?->any());
    var saved = null, getter = null;
    try {
        if (afterAction) saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        sessionStorage.removeItem(KEY);
    } catch (e) {}
    if (saved && Date.now() - (saved.at || 0) > 30 * 60 * 1000) saved = null;

    function save() {
        try {
            sessionStorage.setItem(KEY, JSON.stringify({ at: Date.now(), y: window.scrollY, state: getter ? getter() : null }));
        } catch (e) {}
    }
    window.addEventListener('pagehide', save);

    window.cpKeep = {
        /** حالة الشاشة قبل الحركة، أو null إذا دخلتها دخول عادي */
        take: function () { return saved ? saved.state : null; },
        /** الشاشة بتعطي دالة بترجع حالتها الحالية */
        track: function (fn) { getter = fn; },
    };

    if (saved && saved.y) {
        if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
        window.addEventListener('load', function () {
            requestAnimationFrame(function () { window.scrollTo(0, saved.y); });
        });
    }
})();
</script>
