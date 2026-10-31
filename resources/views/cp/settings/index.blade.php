@extends('cp.layout')
@section('title', 'الإعدادات')
@section('content')
@php
    $groupOptions = $groupRows->map(fn ($g) => ['id' => $g['id'], 'name' => $g['name']])->values();
    $switch = function (array $row) {
        return '<form method="post" action="'.e($row['toggle']).'" class="st-sw-form">'.csrf_field()
            .'<button type="submit" class="st-sw'.($row['active'] ? ' on' : '').'" role="switch" aria-checked="'.($row['active'] ? 'true' : 'false').'" title="'.($row['active'] ? 'شغّالة، اضغط للإطفاء' : 'مطفية، اضغط للتشغيل').'"><i></i></button></form>';
    };
    $uses = fn (int $n) => $n ? 'مستعملة '.$n.' '.($n === 1 ? 'مرة' : ($n <= 10 ? 'مرات' : 'مرة')) : 'مش مستعملة';
@endphp
<div class="cl-page st-page">
    <div class="cl-top">
        <h1 class="cl-h">الإعدادات</h1>
    </div>

    <nav class="cl-seg st-tabs" aria-label="أقسام الإعدادات">
        <a href="{{ route('cp.settings.index', ['tab' => 'services']) }}" class="{{ $tab === 'services' ? 'on' : '' }}">الخدمات</a>
        <a href="{{ route('cp.settings.index', ['tab' => 'methods']) }}" class="{{ $tab === 'methods' ? 'on' : '' }}">طرق الدفع</a>
        <a href="{{ route('cp.settings.index', ['tab' => 'import']) }}" class="{{ $tab === 'import' ? 'on' : '' }}">الاستيراد</a>
    </nav>

    @if($errors->any())
        <p class="cl-dup st-msg"><span class="material-symbols-outlined">error</span> {{ $errors->first() }}</p>
    @endif

    @if($tab === 'services')
        <div class="st-bar">
            <p class="st-note">المستعملة ما بتنحذف، بتنطفى بس: بتختفي من أزرار الإضافة وبتضل بالكشوفات. وتعديل الاسم بيتحدّث على الحركات القديمة.</p>
            <button type="button" class="cp-btn cp-btn-primary" data-open="service">+ خدمة</button>
        </div>

        @foreach($groupRows as $g)
            <section class="st-card">
                <header class="st-card-h">
                    <h2>{{ $g['name'] }}</h2>
                    <span class="st-count">{{ count($g['services']) }} خدمة</span>
                    <div class="st-act">
                        <button type="button" class="cl-more" aria-label="خيارات المجموعة {{ $g['name'] }}">⋯</button>
                        <div class="cl-menu" hidden>
                            <button type="button" data-open="group" data-id="{{ $g['id'] }}" data-name="{{ $g['name'] }}" data-url="{{ $g['update'] }}">تعديل الاسم</button>
                            @if($g['total'] === 0)
                                <button type="button" class="danger" data-del="{{ $g['delete'] }}" data-what="المجموعة «{{ $g['name'] }}»">حذف</button>
                            @else
                                <span class="st-off">الحذف بس للمجموعة الفاضية</span>
                            @endif
                        </div>
                    </div>
                </header>
                @forelse($g['services'] as $s)
                    @include('cp.settings.partials.service-row', ['s' => $s])
                @empty
                    <p class="st-empty">ما فيها خدمات.</p>
                @endforelse
            </section>
        @endforeach

        @if($ungrouped->isNotEmpty() || $groupRows->isEmpty())
            <section class="st-card">
                <header class="st-card-h"><h2>بدون مجموعة</h2><span class="st-count">{{ $ungrouped->count() }} خدمة</span></header>
                @forelse($ungrouped as $s)
                    @include('cp.settings.partials.service-row', ['s' => $s])
                @empty
                    <p class="st-empty">ما في خدمات لسا.</p>
                @endforelse
            </section>
        @endif

        @if($stopped->isNotEmpty())
            <details class="st-card st-stopped">
                <summary class="st-card-h"><h2>موقوفة</h2><span class="st-count">{{ $stopped->count() }} خدمة · ما بتطلع بأزرار الإضافة وبتضل بالكشوفات والتقارير</span></summary>
                @foreach($stopped as $s)
                    @include('cp.settings.partials.service-row', ['s' => $s])
                @endforeach
            </details>
        @endif
    @elseif($tab === 'methods')
        <div class="st-bar">
            <p class="st-note">المستعملة ما بتنحذف، بتنطفى بس: بتختفي من أزرار الدفع وبتضل بالكشوفات.</p>
            <button type="button" class="cp-btn cp-btn-primary" data-open="method">+ طريقة دفع</button>
        </div>
        <section class="st-card">
            @foreach($methods as $m)
                <div class="st-row{{ $m['active'] ? '' : ' is-off' }}">
                    <div class="st-name"><b>{{ $m['name'] }}</b><span>{{ $uses($m['uses']) }}</span></div>
                    {!! $switch($m) !!}
                    <div class="st-act">
                        <button type="button" class="cl-more" aria-label="خيارات {{ $m['name'] }}">⋯</button>
                        <div class="cl-menu" hidden>
                            <button type="button" data-open="method" data-name="{{ $m['name'] }}" data-url="{{ $m['update'] }}">تعديل</button>
                            @if($m['uses'] === 0)
                                <button type="button" class="danger" data-del="{{ $m['delete'] }}" data-what="طريقة الدفع «{{ $m['name'] }}»">حذف</button>
                            @else
                                <span class="st-off">مستعملة، بتقدر تطفيها بس</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </section>
    @else
        <section class="st-card st-import">
            <h2>استيراد الزبائن من إكسيل</h2>
            <p class="st-note">ارفع ملف الإكسيل القديم، وبتشوف معاينة لكل زبون وحركاته قبل ما ينحفظ إشي.</p>
            <a href="{{ route('cp.clients.import') }}" class="cp-btn cp-btn-primary">ابدأ الاستيراد</a>
        </section>
    @endif
</div>

{{-- نافذة الخدمة --}}
<div class="cl-sheet" id="stService" hidden>
    <form class="cl-dialog" method="post" action="{{ route('cp.settings.services.store') }}" role="dialog" aria-modal="true" aria-labelledby="stServiceT">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <h2 id="stServiceT">خدمة جديدة</h2>
        <div class="cl-field">
            <label for="sName">الاسم</label>
            <input id="sName" name="name" maxlength="255" required autocomplete="off">
            <p class="st-warn" id="sDup" hidden>في خدمة بنفس الاسم.</p>
            <p class="st-hint" id="sRen" hidden></p>
        </div>
        <div class="cl-field">
            <label>المجموعة</label>
            <input type="hidden" name="service_group_id" id="sGroup" value="">
            <div class="st-glist" id="sGroups" role="radiogroup" aria-label="المجموعة">
                @foreach($groupOptions as $go)
                    <button type="button" role="radio" data-g="{{ $go['id'] }}">{{ $go['name'] }}</button>
                @endforeach
                <button type="button" role="radio" data-g="">بدون مجموعة</button>
                <button type="button" class="st-gnew" data-g="new">＋ مجموعة جديدة</button>
            </div>
            <input id="sNewGroup" name="new_group_name" maxlength="100" placeholder="اسم المجموعة الجديدة" hidden>
        </div>
        <div class="cl-field">
            <label>طريقة الفوترة</label>
            <div class="cl-seg st-billing">
                <button type="button" data-b="once" class="on">لمرة وحدة</button>
                <button type="button" data-b="monthly">شهرية</button>
            </div>
            <input type="hidden" name="billing" id="sBilling" value="once">
        </div>
        <label class="st-check"><input type="checkbox" name="has_phases" value="1" id="sPhases"> فيها تصميم وتنفيذ</label>
        <div class="cl-field">
            <label for="sPrice">السعر المعتاد</label>
            <input id="sPrice" name="default_price" inputmode="decimal" placeholder="اختياري">
        </div>
        <label class="st-check"><input type="checkbox" name="is_active" value="1" id="sActive" checked> شغّالة</label>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary">حفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" data-close>إلغاء</button>
        </div>
    </form>
</div>

{{-- نافذة الاسم (مجموعة أو طريقة دفع) --}}
<div class="cl-sheet" id="stName" hidden>
    <form class="cl-dialog" method="post" action="" role="dialog" aria-modal="true" aria-labelledby="stNameT">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <h2 id="stNameT"></h2>
        <div class="cl-field">
            <label for="nName">الاسم</label>
            <input id="nName" name="name" maxlength="255" required>
        </div>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary">حفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" data-close>إلغاء</button>
        </div>
    </form>
</div>
<form method="post" id="stDelForm" hidden>@csrf<input type="hidden" name="_method" value="DELETE"></form>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const urls = {
        group: @json(route('cp.settings.groups.store')),
        method: @json(route('cp.settings.methods.store')),
        service: @json(route('cp.settings.services.store')),
    };
    function closeMenus() { document.querySelectorAll('.cl-menu').forEach((m) => { m.hidden = true; }); }
    function show(sheet, action, put) {
        const f = sheet.querySelector('form');
        f.action = action;
        f.querySelector('[name=_method]').value = put ? 'PUT' : 'POST';
        sheet.hidden = false;
        setTimeout(() => (f.querySelector('input[name=name]')).focus(), 30);
    }
    function setBilling(v) {
        $('sBilling').value = v;
        document.querySelectorAll('.st-billing button').forEach((b) => b.classList.toggle('on', b.dataset.b === v));
    }
    const names = @json($serviceNames);
    let editingId = null, oldName = '', titled = 0;
    function setGroup(v) {
        $('sGroup').value = v === 'new' ? '' : v;
        $('sNewGroup').hidden = v !== 'new';
        if (v === 'new') setTimeout(() => $('sNewGroup').focus(), 20); else $('sNewGroup').value = '';
        document.querySelectorAll('#sGroups [data-g]').forEach((b) => { const on = b.dataset.g === v; b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); });
    }
    $('sGroups').addEventListener('click', (e) => { const b = e.target.closest('[data-g]'); if (b) setGroup(b.dataset.g); });
    function checkName() {
        const v = $('sName').value.trim();
        $('sDup').hidden = !v || !names.some((n) => n.name === v && n.id !== editingId);
        const ren = editingId && titled > 0 && v && v !== oldName;
        $('sRen').hidden = !ren;
        if (ren) $('sRen').textContent = `الاسم الجديد بيطلع على ${titled} حركة قديمة كمان.`;
    }
    $('sName').addEventListener('input', checkName);
    document.querySelector('.st-billing').addEventListener('click', (e) => { const b = e.target.closest('[data-b]'); if (b) setBilling(b.dataset.b); });

    document.addEventListener('click', (e) => {
        const more = e.target.closest('.cl-more');
        if (more) { e.stopPropagation(); const m = more.nextElementSibling; const open = m.hidden; closeMenus(); m.hidden = !open; return; }
        const op = e.target.closest('[data-open]');
        if (op) {
            closeMenus();
            const kind = op.dataset.open;
            if (kind === 'service') {
                const edit = !!op.dataset.url;
                $('stServiceT').textContent = edit ? 'تعديل الخدمة' : 'خدمة جديدة';
                $('sName').value = op.dataset.name || '';
                editingId = edit ? +op.dataset.id : null; oldName = op.dataset.name || ''; titled = +(op.dataset.titled || 0);
                setGroup(op.dataset.group || '');
                setBilling(op.dataset.billing || 'once');
                $('sPhases').checked = op.dataset.phases === '1';
                $('sPrice').value = op.dataset.price || '';
                $('sActive').checked = op.dataset.active !== '0';
                checkName();
                show($('stService'), op.dataset.url || urls.service, edit);
            } else {
                const edit = !!op.dataset.url;
                $('stNameT').textContent = kind === 'group' ? (edit ? 'اسم المجموعة' : 'مجموعة جديدة') : (edit ? 'تعديل طريقة الدفع' : 'طريقة دفع جديدة');
                $('nName').value = op.dataset.name || '';
                show($('stName'), op.dataset.url || urls[kind], edit);
            }
            return;
        }
        const del = e.target.closest('[data-del]');
        if (del) {
            closeMenus();
            const msg = `حذف ${del.dataset.what}؟`;
            const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
            ask.then((ok) => { if (ok) { const f = $('stDelForm'); f.action = del.dataset.del; f.submit(); } });
            return;
        }
        if (e.target.closest('[data-close]') || e.target.classList.contains('cl-sheet')) {
            document.querySelectorAll('.cl-sheet').forEach((s) => { s.hidden = true; });
            return;
        }
        if (!e.target.closest('.cl-menu')) closeMenus();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.querySelectorAll('.cl-sheet').forEach((s) => { s.hidden = true; }); });
})();
</script>
@endsection
