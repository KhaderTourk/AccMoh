@extends('cp.layout')
@section('title', 'الإعلانات الممولة')
@section('content')
@php
    $ml = fn ($p) => (int) substr($p, 5).'/'.substr($p, 0, 4);
    $usdFmt = fn ($v) => ((float) $v < 0 ? '−' : '').number_format(abs((float) $v), 2).' $';
    $fb = '<svg class="ad-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.6 1.6-1.6h1.7V4.4c-.3 0-1.3-.1-2.5-.1-2.5 0-4.1 1.5-4.1 4.2v2.3H7.4V14h2.8v8z"/></svg>';
    $ig = '<svg class="ad-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4z"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/></svg>';
    $bank = $methods->first(fn ($m) => str_contains($m->name, 'بنك')) ?? $methods->first();
@endphp
<div class="cl-page ad-page">
    <div class="cl-top">
        <h1 class="cl-h">الإعلانات الممولة</h1>
        <div class="cl-sp"></div>
        <button type="button" class="cp-btn cp-btn-primary" data-new-campaign><span class="material-symbols-outlined">add</span> حملة</button>
    </div>

    @if($errors->any())<p class="ad-err" role="alert">{{ $errors->first('ads') ?: $errors->first() }}</p>@endif

    <div class="cl-tools">
        <div class="cl-seg" role="tablist">
            <a href="{{ route('cp.ads.index') }}" class="{{ $tab === 'campaigns' ? 'on' : '' }}">الحملات</a>
            <a href="{{ route('cp.ads.index', ['tab' => 'invoices']) }}" class="{{ $tab === 'invoices' ? 'on' : '' }}">فواتير ميتا</a>
        </div>
    </div>

    @if($tab === 'campaigns')
        <div class="cl-tools ad-filters">
            <div class="ad-months">
                <button type="button" class="cl-chip" id="adMBtn"><span>كل الشهور</span> ▾</button>
                <div class="ad-mmenu" id="adMMenu" hidden>
                    <label class="all"><input type="checkbox" id="adMAll"> كل الشهور</label>
                    @foreach($periods as $p)<label><input type="checkbox" value="{{ $p }}" @checked(in_array($p, $defaultMonths, true))> {{ $ml($p) }}</label>@endforeach
                    <small>بدون اختيار = كل الشهور</small>
                </div>
            </div>
            <div class="cl-seg" id="adPlat">
                <button type="button" class="on" data-p="">الكل</button>
                <button type="button" data-p="fb">فيس</button>
                <button type="button" data-p="ig">انستا</button>
            </div>
            <input type="search" id="adQ" class="ad-q" placeholder="⌕ بحث بالزبون">
            <button type="button" class="cp-btn cp-btn-ghost" data-open-rec>مطابقة واعتماد</button>
        </div>

        <div class="ad-stats">
            <div><span>الصرف على ميتا</span><b id="tSpend">0.00 $</b><small id="tCount"></small></div>
            <div><span>المطلوب من الزباين</span><b id="tReq">0.00 ₪</b><small>الصرف × سعر البيع</small></div>
            <div><span>تكلفتنا بالشيكل</span><b id="tCost">0.00 ₪</b><small>الصرف × سعر الدولار الفعلي</small></div>
            <div><span>ربح براند</span><b id="tProfit" class="credit">0.00 ₪</b><small id="tPct"></small></div>
        </div>

        <div class="ad-mstate" id="adState">
            <div><b id="msTitle"></b><small id="msSub"></small></div>
            <button type="button" class="cp-btn cp-btn-primary" data-open-rec id="msBtn">طابق واعتمد</button>
        </div>

        <div class="ad-table">
            <div class="ad-tr ad-th"><span>الزبون والحملة</span><span>الصرف</span><span>سعر البيع</span><span>المطلوب</span><span>الربح</span><span></span></div>
            <div id="adRows"></div>
            <div class="ad-tr ad-tf"><span>المجموع</span><span id="fSpend"></span><span></span><span id="fReq"></span><span id="fProfit"></span><span></span></div>
        </div>
    @else
        <div class="ad-invhead">
            <p class="cs-hint">سجّل الفاتورة يوم ما تندفع: بتنخصم بالدولار من حساب {{ $bank?->name ?? 'البنك' }} مباشرة، وبتسدّ حساب ميتا.</p>
            <button type="button" class="cp-btn cp-btn-primary" data-open="adInv"><span class="material-symbols-outlined">add</span> فاتورة</button>
        </div>
        <div class="ad-table ad-inv">
            <div class="ad-tr ad-th"><span>تاريخ الفاتورة</span><span>رقم الفاتورة على ميتا</span><span>من</span><span>المبلغ</span></div>
            @forelse($invoices as $i)
                <div class="ad-tr">
                    <span>{{ format_date($i->invoice_date) }}</span>
                    <span dir="ltr">{{ $i->meta_ref }}</span>
                    <span>{{ $i->paymentMethod?->name }} · بسعر {{ number_format((float) $i->usd_rate, 2) }}</span>
                    <b>{{ $usdFmt($i->amount_usd) }}</b>
                </div>
            @empty
                <p class="cl-empty">ما في فواتير.</p>
            @endforelse
        </div>
        <div class="ad-metabal">
            <div><b>رصيد حساب ميتا</b><small>حملات معتمدة ناقص فواتير مدفوعة</small></div>
            <b class="{{ abs($metaBalance) >= 0.005 ? 'due' : '' }}">{{ abs($metaBalance) >= 0.005 ? $usdFmt($metaBalance) : 'مسدّد' }}</b>
        </div>
    @endif
</div>

{{-- حملة جديدة / تعديل --}}
<div class="cl-sheet" id="adCamp" hidden>
    <form class="cl-dialog" method="post" action="{{ route('cp.ads.campaigns.store') }}" id="adCampForm" role="dialog" aria-modal="true" aria-labelledby="adCampT">
        @csrf
        <input type="hidden" name="_method" id="adCampMethod" value="POST">
        <input type="hidden" name="new_client" id="adNcName"><input type="hidden" name="new_company" id="adNcOrg">
        <input type="hidden" name="new_phone" id="adNcPhone"><input type="hidden" name="new_notes" id="adNcNotes">
        <h2 id="adCampT">حملة جديدة</h2>
        <p class="cs-hint">اسم الحملة هو اسم الزبون</p>
        <p class="ad-warn" id="adCampWarn" hidden></p>
        <div class="cl-field"><label for="adClient">الزبون</label>
            <select id="adClient" name="client_id" class="cs-sel">
                <option value="">اختار الزبون</option>
                @foreach($clients as $c)<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach
                <option value="new">＋ زبون جديد</option>
            </select>
        </div>
        <div class="cl-field"><label for="adPeriod">الشهر</label>
            <select id="adPeriod" name="period" class="cs-sel">@foreach($monthOpts as $p)<option value="{{ $p }}" @selected($p === $thisMonth)>{{ $ml($p) }}</option>@endforeach</select></div>
        <div class="cl-field"><label>وين انعملت</label>
            <div class="ad-toggles">
                <button type="button" class="ad-tg on" data-tg="fb" aria-pressed="true">{!! $fb !!} فيس</button>
                <button type="button" class="ad-tg" data-tg="ig" aria-pressed="false">{!! $ig !!} انستا</button>
            </div>
            <input type="hidden" name="on_facebook" id="adFb" value="1"><input type="hidden" name="on_instagram" id="adIg" value="0">
            <small class="cs-hint" id="adTgHint">حملة على فيس بس</small>
        </div>
        <div class="cs-two">
            <div class="cl-field"><label for="adSpend">مبلغ الصرف</label><input id="adSpend" name="spend_usd" inputmode="decimal" dir="ltr" required placeholder="0.00"><small class="cs-hint">بالدولار، زي لوحة ميتا</small></div>
            <div class="cl-field"><label for="adRate">سعر البيع للدولار</label><input id="adRate" name="sell_rate" inputmode="decimal" dir="ltr" required value="5.00"><small class="cs-hint" id="adSellHint">بينزل آخر سعر استعملته للزبون، وبتعدّله لهالحملة</small></div>
        </div>
        <div class="ad-calc3">
            <div><span>المطلوب من الزبون</span><b id="rReq">0.00 ₪</b></div>
            <div><span>التكلفة بسعر {{ number_format($rate, 2) }}</span><b id="rCost">0.00 ₪</b></div>
            <div><span>ربح براند</span><b id="rProfit">0.00 ₪</b></div>
        </div>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary" id="adSave" disabled>حفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" data-close>إلغاء</button>
        </div>
    </form>
</div>

{{-- زبون جديد من التاب: نفس حقول نافذة الزبون --}}
<div class="cl-sheet" id="adNc" hidden>
    <div class="cl-dialog" role="dialog" aria-modal="true" aria-labelledby="adNcT">
        <h2 id="adNcT">زبون جديد</h2>
        <div class="cl-field"><label for="ncOrg">الجهة <small>اختياري</small></label><input id="ncOrg"></div>
        <div class="cl-field"><label for="ncName">الاسم</label><input id="ncName"></div>
        <div class="cl-field"><label for="ncPhone">الهاتف <small>اختياري</small></label><input id="ncPhone" inputmode="numeric" dir="ltr" maxlength="10" placeholder="05XXXXXXXX">
            <small class="cs-hint ad-bad" id="ncPhoneErr" hidden>الرقم لازم يكون 10 أرقام ويبدأ بـ05</small></div>
        <div class="cl-field"><label for="ncNotes">ملاحظات <small>اختياري</small></label><textarea id="ncNotes" rows="2"></textarea></div>
        <p class="ad-warn" id="ncDup" hidden></p>
        <small class="cs-hint">إذا الجهة فاضية، الاسم بيطلع مكانها بالقائمة.</small>
        <div class="cl-actions">
            <button type="button" class="cp-btn cp-btn-primary" id="ncSave" disabled>حفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" data-close>إلغاء</button>
        </div>
    </div>
</div>

{{-- مطابقة واعتماد --}}
<div class="cl-sheet" id="adRec" hidden>
    <form class="cl-dialog" method="post" id="adRecForm" role="dialog" aria-modal="true" aria-labelledby="adRecT">
        @csrf
        <h2 id="adRecT">مطابقة واعتماد</h2>
        <p class="cs-hint">الضايل من الماضي + حملات الشهر − الفواتير المدفوعة = اللي بيترحل</p>
        @if($openMonths->isEmpty())
            <p class="cl-empty">كل الشهور معتمدة.</p>
        @else
            <div class="cl-field"><label for="recMonth">الشهر</label>
                <select id="recMonth" class="cs-sel">@foreach($openMonths as $p)<option value="{{ $p }}">{{ $ml($p) }}</option>@endforeach</select></div>
            <dl class="ad-recl">
                <div><dt>ضايل من الشهر الماضي</dt><dd id="cPrev"></dd></div>
                <div><dt>＋ حملات الشهر بالنظام</dt><dd id="cCamp"></dd></div>
                <div><dt>− الفواتير المدفوعة</dt><dd id="cInv"></dd></div>
                <div class="ad-exp"><dt>المفروض يترحل للشهر الجاي</dt><dd id="cCarry"></dd></div>
            </dl>
            <div class="cl-field"><label for="rMeta">الضايل على لوحة ميتا</label><input id="rMeta" name="meta_balance" inputmode="decimal" dir="ltr" required placeholder="0.00">
                <small class="cs-hint">المبلغ اللي لسا ما انفوتر، زي ما هو بلوحة الإعلانات</small></div>
            <div class="ad-diff"><span>الفرق</span><b id="cDiff">—</b></div>
            <div class="cl-actions">
                <button type="submit" class="cp-btn cp-btn-primary" id="recOk" disabled>تأكيد واعتماد</button>
                <button type="submit" class="cp-btn cp-btn-ghost" name="skip" value="1" id="recSkip" hidden>تخطي الفرق واعتماد</button>
                <button type="button" class="cp-btn cp-btn-ghost" data-close>إلغاء</button>
            </div>
        @endif
        <details class="ad-lim"><summary>حد الفرق البسيط: {{ rtrim(rtrim(number_format($limit, 2), '0'), '.') }} $</summary>
            <div class="ad-limf"><input form="adLimForm" name="limit" inputmode="decimal" dir="ltr" value="{{ rtrim(rtrim(number_format($limit, 2), '0'), '.') }}"><button form="adLimForm" class="cp-btn cp-btn-ghost">حفظ</button></div>
        </details>
    </form>
    <form id="adLimForm" method="post" action="{{ route('cp.ads.limit') }}" hidden>@csrf</form>
</div>

{{-- فاتورة ميتا --}}
<div class="cl-sheet" id="adInv" hidden>
    <form class="cl-dialog" method="post" action="{{ route('cp.ads.invoices.store') }}" role="dialog" aria-modal="true" aria-labelledby="adInvT">
        @csrf
        <h2 id="adInvT">فاتورة ميتا</h2>
        <div class="cs-two">
            <div class="cl-field"><label for="adInvDate">تاريخ الفاتورة</label><input id="adInvDate" type="date" name="invoice_date" value="{{ now()->toDateString() }}" required></div>
            <div class="cl-field"><label for="adInvAmt">المبلغ</label><input id="adInvAmt" name="amount_usd" inputmode="decimal" dir="ltr" required placeholder="0.00"><small class="cs-hint">بالدولار</small></div>
        </div>
        <div class="cl-field"><label for="adInvRef">رقم الفاتورة على ميتا</label><input id="adInvRef" name="meta_ref" dir="ltr" required></div>
        <div class="cs-two">
            <div class="cl-field"><label for="adInvRate">سعر الدولار يوم الدفع</label><input id="adInvRate" name="usd_rate" inputmode="decimal" dir="ltr" required value="{{ $rate ?: '' }}"><small class="cs-hint">لحساب الربح بالشيكل بس</small></div>
            <div class="cl-field"><label for="adInvSrc">انخصمت من</label>
                <select id="adInvSrc" name="source" class="cs-sel">
                    @foreach($methods as $m)
                        <option value="{{ $m->id }}:USD" @selected($bank && $m->id === $bank->id)>{{ $m->name }} · دولار</option>
                    @endforeach
                    @foreach($methods as $m)
                        <option value="{{ $m->id }}:ILS">{{ $m->name }} · شيكل (صرف لدولار)</option>
                    @endforeach
                </select></div>
        </div>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary">حفظ الفاتورة</button>
            <button type="button" class="cp-btn cp-btn-ghost" data-close>إلغاء</button>
        </div>
    </form>
</div>

<form id="adDel" method="post" hidden>@csrf @method('DELETE')</form>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const C = @json($campaigns), COST = @json($monthCost), REC = @json($rec), RATES = @json($lastRates), CLIENTS = @json($clients);
    const RATE = {{ (float) $rate }}, LIMIT = {{ (float) $limit }};
    const FB = @json($fb), IG = @json($ig);
    const upd = @json(url('cp/ads/campaigns')), store = @json(route('cp.ads.campaigns.store')), approveUrl = @json(url('cp/ads/months'));
    const n2 = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const S = (v) => (v < 0 ? '−' : '') + n2(Math.abs(v)) + ' ₪', D = (v) => (v < 0 ? '−' : '') + n2(Math.abs(v)) + ' $';
    const ml = (p) => (+p.slice(5)) + '/' + p.slice(0, 4);
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const plab = (c) => c.fb && c.ig ? 'فيس وانستا بحملة وحدة' : (c.fb ? 'فيس' : 'انستا');
    const costOf = (c) => c.spend * ((COST[c.period] || {}).rate || RATE);
    const closeMenus = () => document.querySelectorAll('.ad-rmenu').forEach((m) => { m.hidden = true; });
    document.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => { b.closest('.cl-sheet').hidden = true; }));
    document.querySelectorAll('.cl-sheet').forEach((s) => s.addEventListener('click', (e) => { if (e.target === s) s.hidden = true; }));
    document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => { $(b.dataset.open).hidden = false; }));

    /* ===== الحملات ===== */
    if ($('adRows')) {
        let plat = '';
        const boxes = [...$('adMMenu').querySelectorAll('input[value]')];
        const months = () => new Set(boxes.filter((b) => b.checked).map((b) => b.value));
        const syncM = () => { const m = months(); $('adMAll').checked = m.size === boxes.length && boxes.length > 0;
            $('adMBtn').firstElementChild.textContent = !m.size || m.size === boxes.length ? 'كل الشهور' : [...m].map(ml).join(' · '); render(); };
        boxes.forEach((b) => b.addEventListener('change', syncM));
        $('adMAll').addEventListener('change', () => { boxes.forEach((b) => { b.checked = $('adMAll').checked; }); syncM(); });
        $('adMBtn').addEventListener('click', (e) => { e.stopPropagation(); $('adMMenu').hidden = !$('adMMenu').hidden; });
        $('adMMenu').addEventListener('click', (e) => e.stopPropagation());
        document.addEventListener('click', () => { $('adMMenu').hidden = true; closeMenus(); });
        $('adPlat').addEventListener('click', (e) => { const b = e.target.closest('button'); if (!b) return; plat = b.dataset.p;
            $('adPlat').querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b)); render(); });
        $('adQ').addEventListener('input', render);

        function render() {
            const m = months(), q = $('adQ').value.trim(), multi = m.size !== 1;
            const rows = C.filter((c) => (!m.size || m.has(c.period)) && (!plat || c[plat]) && (!q || c.name.includes(q)));
            const groups = {}; rows.forEach((c) => (groups[c.client_id] = groups[c.client_id] || []).push(c));
            let ts = 0, tr = 0, tc = 0;
            $('adRows').innerHTML = Object.values(groups).sort((a, b) => a[0].name.localeCompare(b[0].name, 'ar')).map((list) => {
                let gs = 0, gr = 0, gc = 0;
                const subs = list.map((c) => { const req = c.spend * c.sell, cost = costOf(c); gs += c.spend; gr += req; gc += cost;
                    return `<div class="ad-tr ad-sub"><span>${c.fb ? FB : ''}${c.ig ? IG : ''} ${plab(c)} ${multi ? `<small class="ad-mo">${ml(c.period)}</small>` : ''}<i class="ad-chip ${c.approved ? 'ok' : ''}">${c.approved ? 'معتمد' : 'مسودة'}</i></span>
                        <span dir="ltr">${D(c.spend)}</span><span dir="ltr">× ${+c.sell.toFixed(2)}</span><span>${S(req)}</span><span class="${req - cost < 0 ? 'due' : 'credit'}">${S(req - cost)}</span>
                        <span class="ad-act"><button type="button" class="cl-more ad-more" aria-label="خيارات">⋯</button><span class="ad-rmenu cl-menu" hidden><button type="button" data-ed="${c.id}">تعديل</button><button type="button" class="due" data-del="${c.id}">حذف</button></span></span></div>`; }).join('');
                ts += gs; tr += gr; tc += gc;
                const c0 = list[0];
                return `<div class="ad-tr ad-grp"><span>${c0.url ? `<a href="${c0.url}">${esc(c0.name)}</a>` : esc(c0.name)} <small>${list.length > 1 ? list.length + ' حملات' : 'حملة وحدة'}</small></span>
                    <span dir="ltr">${D(gs)}</span><span></span><span>${S(gr)}</span><span class="${gr - gc < 0 ? 'due' : 'credit'}">${S(gr - gc)}</span><span></span></div>${subs}`;
            }).join('') || '<p class="cl-empty">ما في حملات بهالاختيار</p>';
            $('tSpend').textContent = D(ts); $('tCount').textContent = Object.keys(groups).length + ' زباين · ' + rows.length + ' حملات';
            $('tReq').textContent = S(tr); $('tCost').textContent = S(tc); $('tProfit').textContent = S(tr - tc);
            $('fSpend').textContent = D(ts); $('fReq').textContent = S(tr); $('fProfit').textContent = S(tr - tc);
            const ms = [...new Set(rows.map((c) => c.period))];
            const est = ms.filter((p) => COST[p] && !COST[p].confirmed);
            $('tPct').textContent = !rows.length ? '' : (est.length ? 'تقديري لشهر ' + est.map(ml).join(' · ') + '، لأن جزء من الصرف لسا ما اندفع' : 'كله مؤكد على فواتير مدفوعة');
            const pend = ms.filter((p) => !(REC[p] || {}).approved);
            $('adState').classList.toggle('done', !pend.length);
            $('msTitle').textContent = !ms.length ? '' : (pend.length ? 'لسا مسودة: ' + pend.map(ml).join(' · ') : 'كل الشهور المعروضة معتمدة');
            $('msSub').textContent = !ms.length ? '' : (pend.length ? 'المبالغ ما نزلت على كشوف الزباين. طابق مع فواتير ميتا واعتمد.' : 'نزلت المبالغ على كشوف الزباين وعلى حساب ميتا.');
            $('msBtn').hidden = !pend.length; $('adState').hidden = !ms.length;
        }
        $('adRows').addEventListener('click', (e) => {
            const mb = e.target.closest('.ad-more');
            if (mb) { e.stopPropagation(); const mm = mb.nextElementSibling, o = mm.hidden; closeMenus(); mm.hidden = !o; return; }
            const ed = e.target.closest('[data-ed]'); if (ed) { closeMenus(); openCamp(C.find((c) => c.id === +ed.dataset.ed)); return; }
            const dl = e.target.closest('[data-del]');
            if (dl) { closeMenus(); const c = C.find((x) => x.id === +dl.dataset.del);
                const msg = 'حذف حملة «' + c.name + '» على ' + plab(c) + ' لشهر ' + ml(c.period) + ' بقيمة ' + D(c.spend) + '؟\n'
                    + (c.approved ? 'الشهر معتمد، فبينزل قيد عكسي على كشف الزبون وحساب ميتا، والشهر بيرجع مسودة.' : 'لسا مسودة، فبتنحذف.');
                if (confirm(msg)) { $('adDel').action = upd + '/' + c.id; $('adDel').submit(); } }
        });
        syncM();
    }

    /* ===== نافذة الحملة ===== */
    const tgs = [...document.querySelectorAll('.ad-tg')];
    const on = (k) => tgs.find((t) => t.dataset.tg === k).classList.contains('on');
    function calc() {
        const s = parseFloat($('adSpend').value) || 0, sell = parseFloat($('adRate').value) || 0, fb = on('fb'), ig = on('ig');
        $('adFb').value = fb ? 1 : 0; $('adIg').value = ig ? 1 : 0;
        $('adTgHint').textContent = !fb && !ig ? 'فعّل منصة وحدة على الأقل' : (fb && ig ? 'حملة وحدة على فيس وانستا مع بعض. للحملات المنفصلة سجّل كل وحدة لحالها.' : 'حملة على ' + (fb ? 'فيس' : 'انستا') + ' بس');
        $('adTgHint').classList.toggle('ad-bad', !fb && !ig);
        $('rReq').textContent = S(s * sell); $('rCost').textContent = S(s * RATE);
        const pr = s * sell - s * RATE; $('rProfit').textContent = S(pr); $('rProfit').className = pr < 0 ? 'due' : 'credit';
        $('adSave').disabled = !(s > 0 && sell > 0 && (fb || ig) && ($('adClient').value || $('adNcName').value));
    }
    tgs.forEach((t) => t.addEventListener('click', () => { const v = !t.classList.contains('on'); t.classList.toggle('on', v); t.setAttribute('aria-pressed', v); calc(); }));
    ['adSpend', 'adRate'].forEach((id) => $(id).addEventListener('input', calc));
    const clearNc = () => ['adNcName', 'adNcOrg', 'adNcPhone', 'adNcNotes'].forEach((id) => { $(id).value = ''; });
    function openCamp(c) {
        $('adCampForm').action = c ? upd + '/' + c.id : store; $('adCampMethod').value = c ? 'PUT' : 'POST';
        $('adCampT').textContent = c ? 'تعديل حملة' : 'حملة جديدة'; $('adSave').textContent = c ? 'احفظ التعديل' : 'حفظ';
        $('adCampWarn').hidden = !(c && c.approved);
        if (c && c.approved) $('adCampWarn').textContent = 'شهر ' + ml(c.period) + ' معتمد. التعديل بينزل قيد عكسي على كشف الزبون وحساب ميتا، والشهر بيرجع مسودة لحد ما تطابقه من جديد.';
        clearNc(); $('adClient').value = c ? c.client_id : ''; $('adPeriod').value = c ? c.period : @json($thisMonth);
        tgs.forEach((t) => { const v = c ? !!c[t.dataset.tg] : t.dataset.tg === 'fb'; t.classList.toggle('on', v); t.setAttribute('aria-pressed', v); });
        $('adSpend').value = c ? c.spend.toFixed(2) : ''; $('adRate').value = c ? c.sell.toFixed(2) : '5.00';
        $('adSellHint').textContent = 'بينزل آخر سعر استعملته للزبون، وبتعدّله لهالحملة';
        calc(); $('adCamp').hidden = false; setTimeout(() => $('adClient').focus(), 30);
    }
    document.querySelectorAll('[data-new-campaign]').forEach((b) => b.addEventListener('click', () => openCamp(null)));
    $('adClient').addEventListener('change', () => {
        const v = $('adClient').value;
        if (v === 'new') { $('adClient').value = ''; ['ncOrg', 'ncName', 'ncPhone', 'ncNotes'].forEach((id) => { $(id).value = ''; }); ncCheck(); $('adNc').hidden = false; setTimeout(() => $('ncOrg').focus(), 30); return; }
        clearNc();
        const opt = $('adClient').selectedOptions[0];
        if (v && RATES[v]) { $('adRate').value = Number(RATES[v]).toFixed(2); $('adSellHint').textContent = 'آخر سعر استعملته لـ' + opt.textContent + '، وبتعدّله لهالحملة'; }
        calc();
    });

    /* ===== زبون جديد ===== */
    const norm = (t) => t.replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').replace(/\s+/g, ' ').trim();
    function ncCheck() {
        const ph = $('ncPhone').value.trim(), okP = !ph || /^05\d{8}$/.test(ph);
        $('ncPhoneErr').hidden = okP || ph.length < 10;
        const o = norm($('ncOrg').value), nm = norm($('ncName').value);
        const dup = (o.length >= 3 || nm.length >= 3) ? CLIENTS.find((c) => (o.length >= 3 && norm(c.name).includes(o)) || (nm.length >= 3 && norm(c.name).includes(nm))) : null;
        $('ncDup').hidden = !dup; if (dup) $('ncDup').textContent = 'في زبون شبهه: ' + dup.name + '. تأكد إنه مش نفسه قبل ما تحفظ.';
        $('ncSave').disabled = !$('ncName').value.trim() || !okP;
    }
    ['ncOrg', 'ncName', 'ncPhone'].forEach((id) => $(id).addEventListener('input', ncCheck));
    $('ncSave').addEventListener('click', () => {
        const label = $('ncOrg').value.trim() || $('ncName').value.trim();
        $('adNcName').value = $('ncName').value.trim(); $('adNcOrg').value = $('ncOrg').value.trim();
        $('adNcPhone').value = $('ncPhone').value.trim(); $('adNcNotes').value = $('ncNotes').value.trim();
        let o = $('adClient').querySelector('option[data-new]');
        if (!o) { o = document.createElement('option'); o.dataset.new = '1'; $('adClient').insertBefore(o, $('adClient').querySelector('option[value="new"]')); }
        o.value = ''; o.textContent = label + ' (جديد)'; o.selected = true;
        $('adRate').value = '5.00'; $('adSellHint').textContent = 'بتقدر تعدّله لهالحملة';
        $('adNc').hidden = true; calc();
    });

    /* ===== المطابقة ===== */
    document.querySelectorAll('[data-open-rec]').forEach((b) => b.addEventListener('click', () => { $('adRec').hidden = false; rc(); }));
    function rc() {
        if (!$('recMonth')) return;
        const p = $('recMonth').value, r = REC[p] || { prev: 0, spend: 0, paid: 0, expected: 0 };
        $('adRecForm').action = approveUrl + '/' + p + '/approve';
        $('cPrev').textContent = D(r.prev); $('cCamp').textContent = D(r.spend); $('cInv').textContent = D(r.paid); $('cCarry').textContent = D(r.expected);
        const raw = $('rMeta').value.trim(), has = raw !== '', d = +((parseFloat(raw) || 0) - r.expected).toFixed(2), el = $('cDiff');
        if (!has) { el.textContent = '—'; el.className = ''; }
        else if (Math.abs(d) < 0.005) { el.textContent = 'مطابق ✓'; el.className = 'credit'; }
        else { el.textContent = (d > 0 ? 'ميتا أكثر بـ' : 'ميتا أقل بـ') + D(Math.abs(d)); el.className = Math.abs(d) <= LIMIT ? 'ad-warnc' : 'due'; }
        $('recOk').disabled = !has || Math.abs(d) >= 0.005;
        $('recSkip').hidden = !has || Math.abs(d) < 0.005 || Math.abs(d) > LIMIT;
    }
    if ($('recMonth')) { $('recMonth').addEventListener('change', rc); $('rMeta').addEventListener('input', rc); }
})();
</script>
@endsection
