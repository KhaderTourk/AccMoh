@extends('cp.layout')
@section('title', 'نظام التصميم')
@section('content')
{{-- نظام التصميم (Living Style Guide، 12/11/2026): كل عنصر بالنظام بشكله الحقيقي وبكل حالاته، بنفس الـCSS والأجزاء المشتركة.
     الأرقام كلها وهمية. أي عنصر جديد بينضاف هون بنفس التعديل. الإرشادات المكتوبة: docs/design-system.md --}}
@php
    $tokens = [
        ['--bs-pink', 'وردي الهوية', 'الأزرار الرئيسية، العنصر المفتوح بالقائمة، «عليه» (عليه رصيد إلنا)'],
        ['--bs-navy', 'كحلي الهوية', 'القائمة الجانبية، النص بالوضع الفاتح'],
        ['--bs-credit', 'أخضر', '«إله» (إله رصيد عندنا)، الدخل'],
        ['--bs-bg', 'الخلفية', 'خلفية الصفحة'],
        ['--bs-surface', 'السطح', 'الكروت والأقسام والنوافذ'],
        ['--bs-fg', 'النص', 'النص الأساسي والأرقام'],
        ['--bs-muted', 'نص ثانوي', 'العناوين الصغيرة، التواريخ، الشرح'],
        ['--bs-line', 'الخطوط', 'حدود الكروت وفواصل الأسطر'],
        ['--bs-chip', 'الشرائح', 'خلفية التابات والتاب المختار'],
        ['--bs-field', 'الحقول', 'خلفية الحقول وأسطر العملات'],
        ['--bs-hover', 'التمرير', 'لون السطر لما تمرّ عليه'],
    ];
    $places = [['الدرج', '#4C8DFF', 59, '18,000.00'], ['الخزانة', '#7C5CFF', 41, '10,435.00']];
    $palette = ['#4C8DFF', '#7C5CFF', '#F5A524', '#22B8CF', '#94A3B8', '#C084FC'];
    $icons = ['home', 'drawers', 'work', 'person', 'transfer', 'exchange', 'ledger', 'reports', 'gear', 'users', 'ads', 'api', 'offline', 'edit', 'check', 'close', 'back', 'chevron', 'more', 'moon', 'logout'];
@endphp
<div class="cl-page bl-page sg-page">
    <div class="cl-top">
        <h1 class="cl-h">نظام التصميم</h1>
        <span class="sg-sub">كل عناصر النظام بشكلها الحقيقي · الأرقام وهمية</span>
    </div>

    <nav class="sg-nav" aria-label="أقسام نظام التصميم">
        @foreach(['colors' => 'الألوان', 'type' => 'الخط', 'icons' => 'الأيقونات', 'buttons' => 'الأزرار', 'fields' => 'الحقول', 'filters' => 'الفلاتر', 'cards' => 'الكروت', 'rows' => 'الأسطر', 'menus' => 'القوائم', 'dialogs' => 'النوافذ', 'states' => 'الحالات', 'rules' => 'القواعد'] as $id => $label)
            <a href="#sg-{{ $id }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- 1. الألوان --}}
    <section class="bl-sec" id="sg-colors">
        <div class="bl-sh"><div><h2>الألوان</h2><small>من متغيرات brand.css. كل لون إله نسخة للوضع الغامق. بدّل الوضع من القمر تشوفها.</small></div></div>
        <div class="sg-swatches">
            @foreach($tokens as [$var, $name, $use])
                <div class="sg-sw"><i style="background: var({{ $var }})"></i><b>{{ $name }}</b><code dir="ltr">{{ $var }}</code><small>{{ $use }}</small></div>
            @endforeach
        </div>
        <div class="sg-sub2">اتجاه الرصيد: اللون لحاله بيحكي الاتجاه بالشاشات، بدون كلمة «عليه/إله». الكلمات بس بالكشوفات والـPDF.</div>
        <div class="sg-row">
            <span class="sg-pill"><b class="due" dir="ltr">₪ 1,250.00</b><small>عليه (لإلنا)</small></span>
            <span class="sg-pill"><b class="credit" dir="ltr">₪ 820.00</b><small>إله (علينا)</small></span>
            <span class="sg-pill"><b class="zero" dir="ltr">₪ 4.00</b><small>مسدّد (فرق ≤ 10 ₪)</small></span>
        </div>
        <div class="sg-sub2">ألوان الأماكن والرسوم: بدون وردي الهوية وبدون أخضر الأرصدة.</div>
        <div class="sg-row">@foreach($palette as $c)<span class="sg-dot" style="background: {{ $c }}" title="{{ $c }}"></span>@endforeach</div>
    </section>

    {{-- 2. الخط --}}
    <section class="bl-sec" id="sg-type">
        <div class="bl-sh"><div><h2>الخط</h2><small>IBM Plex Sans Arabic. الأرقام بعرض ثابت (tabular-nums) عشان تصطف.</small></div></div>
        <div class="sg-type">
            <div><span class="cl-h">عنوان الصفحة</span><small>cl-h · 1.75rem · 700</small></div>
            <div><span class="sg-h2">عنوان القسم</span><small>bl-sh h2 · 1.05rem · 700</small></div>
            <div><span>نص عادي للأسطر والبيان</span><small>0.9rem · 400</small></div>
            <div><span class="sg-muted">نص ثانوي: تاريخ، درج، ملاحظة</span><small>0.76–0.85rem · muted</small></div>
            <div><b class="sg-big" dir="ltr">₪ 82,318.14</b><small>رقم كبير (المجموع) · 2.1rem · 700</small></div>
            <div><b class="sg-num" dir="ltr">₪ 1,460.00</b><small>رقم بكرت · 1.12–1.6rem · 600–700</small></div>
        </div>
        <div class="sg-sub2">التاريخ دايماً أرقام: 12/11/2026 (يوم/شهر/سنة بشرطة مايلة)، بدون أسماء أشهر. «اليوم · 12/11/2026» و«مبارح · 11/11/2026» بالحركات.</div>
    </section>

    {{-- 3. الأيقونات --}}
    <section class="bl-sec" id="sg-icons">
        <div class="bl-sh"><div><h2>الأيقونات</h2><small>خطوط رفيعة من cp.partials.bs-icon. استعملها بالاسم.</small></div></div>
        <div class="sg-icons">
            @foreach($icons as $ic)<span>@include('cp.partials.bs-icon', ['name' => $ic, 'size' => 20])<code dir="ltr">{{ $ic }}</code></span>@endforeach
        </div>
    </section>

    {{-- 4. الأزرار --}}
    <section class="bl-sec" id="sg-buttons">
        <div class="bl-sh"><div><h2>الأزرار</h2><small>زر رئيسي واحد بالشاشة. بدون أزرار مكررة لنفس الإشي.</small></div></div>
        <div class="sg-demo">
            <div class="sg-item"><button type="button" class="bs-btn">＋ زبون جديد</button><small>bs-btn · إضافة بأعلى القائمة</small></div>
            <div class="sg-item"><span class="cl-actions sg-inline"><button type="button" class="cp-btn cp-btn-primary">حفظ</button><button type="button" class="cp-btn cp-btn-ghost">إلغاء</button></span><small>cl-actions · حفظ/إلغاء بالنوافذ</small></div>
            <div class="sg-item"><span class="cl-actions sg-inline"><button type="button" class="cp-btn cp-btn-primary" disabled>حفظ</button></span><small>حفظ معطّل لحد ما الحقول المطلوبة تتعبّى</small></div>
            <div class="sg-item"><span class="sg-inline"><button type="button" class="cs-btn svc"><span class="material-symbols-outlined">add</span> خدمة</button><button type="button" class="cs-btn pay"><span class="material-symbols-outlined">add</span> دفعة</button><button type="button" class="cs-btn other"><span class="material-symbols-outlined">layers</span> أخرى</button></span><small>cs-btn · إضافة بصفحة الزبون/الموظف/المورد/الشخص</small></div>
            <div class="sg-item"><button type="button" class="bl-tbtn">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 14]) تعديل التوزيع</button><small>bl-tbtn · زر نصي أبيض بعنوان القسم، وردي لما تمرّ عليه</small></div>
            <div class="sg-item"><span class="sg-inline"><button type="button" class="bl-pe" aria-label="تعديل">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 15])</button><button type="button" class="cl-more" aria-label="خيارات">⋯</button></span><small>bl-pe قلم تعديل بنفس السطر · cl-more «⋯» للخيارات الإضافية</small></div>
            <div class="sg-item"><span class="sg-inline"><a class="cp-btn cp-btn-ghost" href="#sg-buttons">دخل الخدمات</a><a class="cp-btn cp-btn-primary" href="#sg-buttons">تصدير PDF</a></span><small>أزرار رأس الصفحة (التقارير)</small></div>
        </div>
    </section>

    {{-- 5. الحقول --}}
    <section class="bl-sec" id="sg-fields">
        <div class="bl-sh"><div><h2>الحقول</h2><small>ما في قوائم متصفح: كل select بيتحوّل لحاله لقائمة براند (cp-select.js)، وعالموبايل بتطلع من تحت.</small></div></div>
        <div class="sg-fields">
            <div class="cl-field"><label for="sgName">الاسم</label><input id="sgName" placeholder="اكتب الاسم"></div>
            <div class="cl-field"><label for="sgPhone">الهاتف</label><input id="sgPhone" inputmode="numeric" dir="ltr" placeholder="اختياري · 05xxxxxxxx"><small class="cl-err">الرقم لازم يكون 10 خانات ويبدأ بـ 05</small></div>
            <div class="cl-field"><label for="sgDrawer">الدرج</label><select id="sgDrawer"><option>نقدي</option><option>بنك فلسطين</option><option>بال باي</option><option>جوال باي</option></select></div>
            <div class="cl-field"><label for="sgCur">العملة</label><select id="sgCur"><option>شيكل ₪</option><option>دولار $</option><option>دينار JD</option></select></div>
            <div class="cl-field"><label for="sgNote">البيان</label><textarea id="sgNote" rows="2" placeholder="شو صار؟ (إلزامي)"></textarea></div>
            <div class="cl-field"><label>بحث</label><label class="cl-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" placeholder="ابحث بالجهة أو الاسم أو الهاتف"></label></div>
        </div>
    </section>

    {{-- 6. الفلاتر --}}
    <section class="bl-sec" id="sg-filters">
        <div class="bl-sh"><div><h2>الفلاتر</h2><small>بدون إعادة تحميل (cp-soft.js): بيتبدّل بس الجزء المتأثر، والرابط بيحفظ الفلتر. بعد أي حفظ أو حذف «ضل مكانك».</small></div></div>
        <div class="sg-demo">
            <div class="sg-item"><div class="cl-seg sg-seg" role="tablist"><button type="button" class="on">عليه رصيد</button><button type="button">إله رصيد</button><button type="button">مسدّد</button></div><small>cl-seg · تابات القوائم (بدون «الكل»)</small></div>
            <div class="sg-item"><div class="bl-seg2"><a href="#sg-filters" class="on">الكل</a><a href="#sg-filters">شيكل</a><a href="#sg-filters">دولار</a><a href="#sg-filters">دينار</a></div><small>bl-seg2 · تابات العملة</small></div>
            <div class="sg-item">@include('cp.partials.period-filter')<small>فلتر الفترة: كل الوقت/هالشهر/الشهر الماضي/آخر 3 شهور/هالسنة/السنة الماضية + من/لغاية + تطبيق</small></div>
            <div class="sg-item">
                <div class="bl-fm">
                    <button type="button" class="cs-range-btn" data-fm>كل الأدراج @include('cp.partials.bs-icon', ['name' => 'chevron', 'size' => 12])</button>
                    <div class="cl-menu" hidden><a href="#sg-filters" class="on">كل الأدراج</a><a href="#sg-filters">نقدي</a><a href="#sg-filters">بنك فلسطين</a><a href="#sg-filters">بال باي</a><a href="#sg-filters">جوال باي</a></div>
                </div>
                <small>bl-fm · قائمة فلتر (النوع، الدرج، الطرف)</small>
            </div>
            <div class="sg-item"><div class="bl-pages sg-pages">@include('cp.partials.bl-pages', ['p' => $pages])</div><small>bl-pages · الصفحات</small></div>
        </div>
    </section>

    {{-- 7. الكروت --}}
    <section class="sg-block" id="sg-cards">
        <h2 class="sg-bh">الكروت <small>الأرصدة متموّهة افتراضياً (bl-money). اكبس على الرقم أو العين تظهر.</small></h2>
        <div class="bl-card bl-strip">
            <div class="bl-stot">
                <span class="bl-name">المجموع بسعر اليوم</span>
                <b class="bl-money bl-big" dir="ltr">₪ 82,148.14</b>
                <span class="bl-mix"><span><i>شيكل</i><b class="bl-money" dir="ltr">60,512.00</b></span><span><i>دولار</i><b class="bl-money" dir="ltr">4,200.00</b></span><span><i>دينار</i><b class="bl-money" dir="ltr">1,350.00</b></span></span>
            </div>
            <div class="db2-month">
                <span class="bl-rl">سعر الصرف</span>
                <div><span class="bl-cb"><span class="bl-rl">سعر صرف الدولار</span><b dir="ltr">₪ 3.700</b></span><span class="bl-cb"><span class="bl-rl">سعر صرف الدينار</span><b dir="ltr">₪ 5.210</b></span></div>
            </div>
        </div>
        <small class="sg-cap">bl-strip · شريط الملخص: رقم كبير يمين، وتفاصيل يسار.</small>
        <div class="bl-grid sg-grid">
            @foreach([['نقدي', '65,702.00', ['18,000.00', '600.00', '0.00']], ['بنك فلسطين', '12,900.86', ['12,900.86', '0.00', '0.00']]] as [$name, $tot, $cur])
                <div class="bl-card">
                    <a class="bl-main" href="#sg-cards">
                        <span class="bl-name">{{ $name }}</span>
                        <b class="bl-money">{{ $tot }} ₪</b>
                        <span class="bl-curs">
                            @foreach(['شيكل ₪', 'دولار $', 'دينار JD'] as $i => $c)<span class="bl-cur {{ $cur[$i] === '0.00' ? 'zero' : '' }}"><span>{{ $c }}</span><b class="bl-money" dir="ltr">{{ $cur[$i] }}</b></span>@endforeach
                        </span>
                        <span class="bl-share"><span class="bl-bar"><i style="width: {{ $loop->first ? 80 : 16 }}%"></i></span><span dir="ltr">{{ $loop->first ? 80 : 16 }}%</span></span>
                    </a>
                    <button type="button" class="cl-more" aria-label="خيارات {{ $name }}">⋯</button>
                    <div class="cl-menu" hidden><button type="button">تسوية رصيد</button></div>
                </div>
            @endforeach
        </div>
        <small class="sg-cap">bl-card · كرت الدرج: الاسم، المجموع، كل عملة بسطر حتى لو صفر، وخط الحصة. ترتيب الأدراج دايماً: نقدي، بنك فلسطين، بال باي، جوال باي.</small>

        <section class="bl-sec">
            <div class="bl-sh"><div><h2>الأماكن</h2><small>للعرض بس، ما بيأثّر على الأرصدة</small></div><button type="button" class="bl-tbtn">@include('cp.partials.bs-icon', ['name' => 'edit', 'size' => 14]) تعديل التوزيع</button></div>
            <div class="bl-stk">@foreach($places as $p)<i style="width: {{ $p[2] }}%; background: {{ $p[1] }}"></i>@endforeach</div>
            @foreach($places as $p)
                <div class="bl-pl"><span class="bl-pn"><i style="background: {{ $p[1] }}"></i>{{ $p[0] }}</span><span class="bl-pa"><b class="bl-money" dir="ltr">₪ {{ $p[3] }}</b></span><span class="bl-pp" dir="ltr">{{ $p[2] }}%</span></div>
            @endforeach
            <div class="bl-pl free"><span class="bl-pn"><i></i>غير موزّع</span><span class="bl-pa"><b class="bl-ok">✓ كله موزّع</b></span><span class="bl-pp" dir="ltr">0%</span></div>
        </section>
    </section>

    {{-- 8. الأسطر --}}
    <section class="bl-sec bl-led" id="sg-rows">
        <div class="bl-sh"><div><h2>الأسطر والحركات</h2><small>مجمّعة باليوم. مبالغ الحركات ظاهرة (مش متموّهة) وهادية. الدخل أخضر بإشارة +.</small></div></div>
        <div class="bl-lrow bl-lhead"><span>البيان</span><span>المبلغ</span><span>الرصيد بعدها</span></div>
        <div class="bl-lday">اليوم · 12/11/2026</div>
        <div class="bl-lrow"><span class="bl-ldesc">دفعة واردة من مطعم الزيتونة<small>عن شهر 10</small></span><span class="bl-lamt in" dir="ltr">+₪ 1,000.00</span><span class="bl-lafter"><em>بعدها </em><span dir="ltr">₪ 28,436.00</span></span></div>
        <div class="bl-lrow"><span class="bl-ldesc">تحويل لبنك فلسطين</span><span class="bl-lamt" dir="ltr">−₪ 1.00</span><span class="bl-lafter"><em>بعدها </em><span dir="ltr">₪ 27,436.00</span></span></div>
        <div class="bl-lday">مبارح · 11/11/2026</div>
        <div class="bl-lrow bl-trow">
            <span class="bl-ldesc"><span class="bl-tic">@include('cp.partials.bs-icon', ['name' => 'transfer', 'size' => 16])</span><span><b>نقدي ← بال باي</b><small>رسوم ₪ 5.00</small></span></span>
            <span class="bl-lamt" dir="ltr">₪ 500.00</span>
            <span class="cl-mwrap"><button type="button" class="cl-more" aria-label="خيارات" data-more>⋯</button><div class="cl-menu" hidden><button type="button" class="danger" data-sg-confirm="بدك تلغي التحويل؟ المبلغ برجع للدرج اللي طلع منه.">إلغاء التحويل</button></div></span>
        </div>
        <div class="bl-lrow bl-trow off">
            <span class="bl-ldesc"><span class="bl-tic">@include('cp.partials.bs-icon', ['name' => 'exchange', 'size' => 16])</span><span><b>تحويل عملات · نقدي</b><em class="bl-off">ملغي</em><small>السعر 3.7</small></span></span>
            <span class="bl-lamt" dir="ltr">$ 100.00<small>→ ₪ 370.00</small></span>
            <span class="cl-mwrap"></span>
        </div>
        <div class="bl-lday">قائمة الأطراف</div>
        <div class="cl-list sg-list">
            <div class="cl-row" tabindex="0"><div class="cl-av">م</div><div class="cl-who"><b><span class="cl-star">★</span> مطعم الزيتونة</b><span>أبو خالد</span></div><div class="cl-amt-wrap"><div class="cl-amt due">₪ 1,050.00</div></div><button type="button" class="cl-more" aria-label="خيارات">⋯</button><div class="cl-menu" hidden><a href="#sg-rows">تصدير كشف حساب</a><button type="button">تعديل</button><button type="button">إلغاء التثبيت</button><hr><button type="button" class="danger">أرشفة</button></div></div>
            <div class="cl-row" tabindex="0"><div class="cl-av">ر</div><div class="cl-who"><b>رامي مصمم</b><span></span></div><div class="cl-amt-wrap"><div class="cl-amt credit">₪ 920.00</div></div><button type="button" class="cl-more" aria-label="خيارات">⋯</button><div class="cl-menu" hidden><button type="button">تعديل</button></div></div>
            <div class="cl-row is-arch" tabindex="0"><div class="cl-av">ش</div><div class="cl-who"><b>شركة قديمة <em class="cs-arch">مؤرشف</em></b><span></span></div><div class="cl-amt zero">مسدّد</div><button type="button" class="cl-more" aria-label="خيارات">⋯</button><div class="cl-menu" hidden><button type="button">استرجاع من الأرشيف</button></div></div>
        </div>
    </section>

    {{-- 9. القوائم --}}
    <section class="bl-sec" id="sg-menus">
        <div class="bl-sh"><div><h2>القوائم</h2><small>كل قائمة بتضل جوا الشاشة. Esc أو كبسة برا بتسكّرها. الخيار الخطر أحمر وبآخر القائمة بعد خط.</small></div></div>
        <div class="sg-demo">
            <div class="sg-item cs-add">
                <button type="button" class="cs-btn pay" data-sg-menu="sgM1"><span class="material-symbols-outlined">add</span> دفعة</button>
                <div class="cl-menu cs-menu" id="sgM1" hidden><button type="button">نقدي</button><button type="button">بنك فلسطين</button><button type="button">بال باي</button><button type="button">جوال باي</button></div>
                <small>cs-menu · قائمة زر إضافة</small>
            </div>
            <div class="sg-item cs-add">
                <button type="button" class="cl-more" data-sg-menu="sgM2" aria-label="خيارات">⋯</button>
                <div class="cl-menu cs-menu" id="sgM2" hidden><a href="#sg-menus">تعديل البيانات</a><a href="#sg-menus">تصدير كشف حساب</a><hr><button type="button" class="danger">أرشفة</button></div>
                <small>cl-menu · خيارات «⋯»</small>
            </div>
        </div>
    </section>

    {{-- 10. النوافذ --}}
    <section class="bl-sec" id="sg-dialogs">
        <div class="bl-sh"><div><h2>النوافذ والتأكيد</h2><small>نافذة وحدة لكل عملية. التأكيد بنافذة براند، مش نافذة المتصفح.</small></div></div>
        <div class="sg-demo">
            <div class="sg-item"><button type="button" class="cp-btn cp-btn-primary" id="sgOpen">افتح نافذة</button><small>cl-sheet + cl-dialog · إضافة وتعديل</small></div>
            <div class="sg-item"><button type="button" class="cp-btn cp-btn-ghost" data-sg-confirm="بدك تحذف الحركة؟">افتح تأكيد</button><small>cpConfirm · قبل الحذف والإلغاء</small></div>
        </div>
    </section>

    {{-- 11. الحالات --}}
    <section class="bl-sec" id="sg-states">
        <div class="bl-sh"><div><h2>الحالات والرسائل</h2><small>كلام عامي قصير يحكي شو صار وشو تعمل.</small></div></div>
        <div class="sg-states">
            <div class="cp-flash cp-flash-success"><span>انحفظت الدفعة.</span></div>
            <div class="cp-flash cp-flash-error"><span>ما انحفظت: المبلغ لازم يكون أكبر من صفر.</span></div>
            <p class="cl-dup"><span class="material-symbols-outlined">info</span> في زبون مشابه: <b>مطعم الزيتونة</b></p>
            <p class="cl-empty">ما في حركات بهالفترة.</p>
            <p class="bl-open">رصيد سابق: <span dir="ltr">₪ 12,000.00</span></p>
            <div class="sg-row"><em class="bl-off">ملغي</em><em class="cs-arch">مؤرشف</em><b class="bl-ok">✓ كله موزّع</b><span class="st-sw on" role="switch" aria-checked="true"><i></i></span><span class="st-sw" role="switch" aria-checked="false"><i></i></span></div>
        </div>
    </section>

    {{-- 12. القواعد --}}
    <section class="bl-sec" id="sg-rules">
        <div class="bl-sh"><div><h2>القواعد</h2><small>النسخة الكاملة بـ docs/design-system.md</small></div></div>
        <dl class="bl-kv sg-rules">
            <div><dt>الاتجاه</dt><dd>كل إشي من اليمين لليسار: الأعمدة، الأزرار، الخيارات، والأرقام.</dd></div>
            <div><dt>الأرصدة</dt><dd>متموّهة افتراضياً، والعين بتظهرها. الحركات مش متموّهة.</dd></div>
            <div><dt>الألوان</dt><dd>عليه وردي، إله أخضر، مسدّد رمادي. وردي الهوية ما بينستعمل للأماكن ولا للرسوم.</dd></div>
            <div><dt>القوائم</dt><dd>ما في قوائم متصفح. كل قائمة بستايل براند وبتضل جوا الشاشة.</dd></div>
            <div><dt>الفلاتر</dt><dd>بدون إعادة تحميل، والرابط بيحفظها. بعد أي حفظ «ضل مكانك».</dd></div>
            <div><dt>التواريخ</dt><dd>12/11/2026، بدون أسماء أشهر.</dd></div>
            <div><dt>العملات</dt><dd>الحسابات بالشيكل بس. الأدراج بعملاتها الحقيقية (شيكل، دولار، دينار).</dd></div>
            <div><dt>عنصر جديد</dt><dd>أي عنصر جديد بالنظام بينضاف لهالصفحة بنفس التعديل.</dd></div>
        </dl>
    </section>
</div>

<div class="cl-sheet" id="sgSheet" hidden>
    <form class="cl-dialog" id="sgForm" novalidate>
        <h2>دفعة · مطعم الزيتونة</h2>
        <div class="cl-field"><label for="sgAmt">المبلغ</label><input id="sgAmt" inputmode="decimal" dir="ltr" placeholder="0.00"></div>
        <div class="cl-field"><label for="sgM">الدرج</label><select id="sgM"><option>نقدي</option><option>بنك فلسطين</option><option>بال باي</option><option>جوال باي</option></select></div>
        <div class="cl-field"><label for="sgWhat">البيان</label><input id="sgWhat" placeholder="إلزامي"></div>
        <div class="cl-actions"><button type="submit" class="cp-btn cp-btn-primary" id="sgSave" disabled>حفظ</button><button type="button" class="cp-btn cp-btn-ghost" id="sgCancel">إلغاء</button></div>
    </form>
</div>

@include('cp.partials.period-filter-go', ['from' => null, 'to' => null, 'base' => route('cp.style')])
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const closeAll = (except) => document.querySelectorAll('.sg-page .cl-menu:not(.cs-range-menu)').forEach((m) => { if (m !== except) m.hidden = true; });
    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-sg-menu], .sg-page .cl-more, [data-fm]');
        if (t && t.closest('.sg-page')) {
            e.stopPropagation();
            const m = t.dataset.sgMenu ? $(t.dataset.sgMenu) : t.nextElementSibling;
            const open = m.hidden; closeAll(m); m.hidden = !open; return;
        }
        const c = e.target.closest('[data-sg-confirm]');
        if (c) { closeAll(); window.cpConfirm && cpConfirm(c.dataset.sgConfirm); return; }
        if (!e.target.closest('.cl-menu')) closeAll();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeAll(); $('sgSheet').hidden = true; } });
    // روابط الأمثلة ما بتعمل إشي.
    document.querySelectorAll('.sg-page a[href^="#sg-"]:not(.sg-nav a)').forEach((a) => a.addEventListener('click', (e) => e.preventDefault()));
    const check = () => { $('sgSave').disabled = !(parseFloat($('sgAmt').value) > 0 && $('sgWhat').value.trim()); };
    $('sgOpen').addEventListener('click', () => { $('sgSheet').hidden = false; $('sgAmt').focus(); });
    $('sgCancel').addEventListener('click', () => { $('sgSheet').hidden = true; });
    $('sgSheet').addEventListener('click', (e) => { if (e.target === $('sgSheet')) $('sgSheet').hidden = true; });
    ['sgAmt', 'sgWhat'].forEach((id) => $(id).addEventListener('input', check));
    $('sgForm').addEventListener('submit', (e) => { e.preventDefault(); $('sgSheet').hidden = true; });
})();
</script>
@endsection
