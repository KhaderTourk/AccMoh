@extends('cp.layout')
@section('title', 'الإعلانات الممولة')
@section('content')
@php
    $ils = fn ($v) => ((float) $v < 0 ? '−' : '').number_format(abs((float) $v), 2).' ₪';
    $usd = fn ($v) => ((float) $v < 0 ? '−' : '').number_format(abs((float) $v), 2).' $';
    $ml = fn ($p) => (int) substr($p, 5).'/'.substr($p, 0, 4);
    $q = fn (array $over) => route('cp.ads.index', array_filter(array_merge(['tab' => $tab, 'm' => $months, 'p' => $platform], $over), fn ($v) => $v !== null && $v !== [] && $v !== ''));
    $fb = '<svg class="ad-ico" viewBox="0 0 24 24" aria-label="فيس"><path fill="currentColor" d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.6 1.6-1.6h1.7V4.4c-.3 0-1.3-.1-2.5-.1-2.5 0-4.1 1.5-4.1 4.2v2.3H7.4V14h2.8v8z"/></svg>';
    $ig = '<svg class="ad-ico" viewBox="0 0 24 24" aria-label="انستا"><path fill="none" stroke="currentColor" stroke-width="2" d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4z"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/></svg>';
    $monthOpts = collect(range(-12, 1))->map(fn ($i) => now()->startOfMonth()->addMonthsNoOverflow($i)->format('Y-m'))->merge($periods)->unique()->sortDesc()->values();
@endphp
<div class="cl-page ad-page">
    <div class="cl-top">
        <h1 class="cl-h">الإعلانات الممولة</h1>
        <div class="cl-sp"></div>
        @if($tab === 'invoices')
            <button type="button" class="cp-btn cp-btn-primary" data-open="adInv"><span class="material-symbols-outlined">add</span> فاتورة ميتا</button>
        @elseif($tab === 'campaigns')
            <button type="button" class="cp-btn cp-btn-primary" data-new-campaign><span class="material-symbols-outlined">add</span> حملة</button>
        @endif
    </div>

    @if($errors->has('ads'))<p class="ad-err" role="alert">{{ $errors->first('ads') }}</p>@endif

    <div class="cl-tools">
        <div class="cl-seg" role="tablist">
            <a href="{{ route('cp.ads.index', ['tab' => 'campaigns']) }}" class="{{ $tab === 'campaigns' ? 'on' : '' }}">الحملات</a>
            <a href="{{ route('cp.ads.index', ['tab' => 'invoices']) }}" class="{{ $tab === 'invoices' ? 'on' : '' }}">فواتير ميتا</a>
            <a href="{{ route('cp.ads.index', ['tab' => 'match']) }}" class="{{ $tab === 'match' ? 'on' : '' }}">المطابقة</a>
        </div>
        @if($tab === 'campaigns')
            <details class="ad-months">
                <summary class="cl-chip">{{ $months ? collect($months)->map($ml)->implode('، ') : 'كل الشهور' }}</summary>
                <form method="get" action="{{ route('cp.ads.index') }}">
                    <input type="hidden" name="tab" value="campaigns">@if($platform)<input type="hidden" name="p" value="{{ $platform }}">@endif
                    @forelse($periods as $p)
                        <label><input type="checkbox" name="m[]" value="{{ $p }}" @checked(in_array($p, $months, true))> {{ $ml($p) }}</label>
                    @empty
                        <small>ما في شهور لسا.</small>
                    @endforelse
                    <button class="cp-btn cp-btn-primary">عرض</button>
                </form>
            </details>
            <div class="cl-seg">
                <a href="{{ $q(['p' => null]) }}" class="{{ $platform ? '' : 'on' }}">الكل</a>
                <a href="{{ $q(['p' => 'fb']) }}" class="{{ $platform === 'fb' ? 'on' : '' }}">فيس</a>
                <a href="{{ $q(['p' => 'ig']) }}" class="{{ $platform === 'ig' ? 'on' : '' }}">انستا</a>
            </div>
        @endif
    </div>

    @if($tab === 'campaigns')
        <div class="ad-groups">
            @forelse($groups as $clientId => $rows)
                @php($client = $rows->first()->client)
                <section class="ad-group">
                    <header>
                        <a href="{{ $client ? route('cp.clients.show', $client) : '#' }}">{{ $client?->directoryLabel() ?? 'زبون محذوف' }}</a>
                        <span><b>{{ $ils($rows->sum(fn ($c) => $c->billed())) }}</b> <small>{{ $usd($rows->sum('spend_usd')) }}</small></span>
                    </header>
                    @foreach($rows as $c)
                        <div class="ad-row">
                            <span class="ad-month">{{ $ml($c->period) }}</span>
                            <span class="ad-plat">@if($c->on_facebook){!! $fb !!}@endif @if($c->on_instagram){!! $ig !!}@endif</span>
                            <span class="ad-calc" dir="ltr">{{ $usd($c->spend_usd) }} × {{ rtrim(rtrim(number_format((float) $c->sell_rate, 4), '0'), '.') }}</span>
                            <b>{{ $ils($c->billed()) }}</b>
                            @if(in_array($c->period, $approved, true))<span class="material-symbols-outlined ad-lock" title="الشهر معتمد">lock</span>@endif
                            <button type="button" class="cl-more" aria-label="خيارات">⋯</button>
                            <div class="cl-menu" hidden>
                                <button type="button" data-edit="{{ json_encode(['id' => $c->id, 'client_id' => $c->client_id, 'period' => $c->period, 'fb' => $c->on_facebook, 'ig' => $c->on_instagram, 'spend' => (float) $c->spend_usd, 'rate' => (float) $c->sell_rate, 'notes' => $c->notes, 'approved' => in_array($c->period, $approved, true)]) }}">تعديل</button>
                                <form method="post" action="{{ route('cp.ads.campaigns.destroy', $c) }}" data-confirm="{{ in_array($c->period, $approved, true) ? 'الشهر معتمد. الحذف بيرجّعه مسودة وبتحتاج تعتمده من جديد. متأكد؟' : 'حذف الحملة؟' }}">
                                    @csrf @method('DELETE')<button type="submit" class="due">حذف</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </section>
            @empty
                <p class="cl-empty">ما في حملات. اضغط «حملة» لتضيف أول وحدة.</p>
            @endforelse
        </div>
    @elseif($tab === 'invoices')
        <div class="cl-list bl-ledger">
            <div class="bl-row bl-head"><span>التاريخ</span><span>الفاتورة</span><span>بالدولار</span><span>بالشيكل</span></div>
            @forelse($invoices as $i)
                <div class="bl-row">
                    <span class="bl-date">{{ format_date($i->invoice_date) }}</span>
                    <span class="bl-desc">{{ $i->meta_ref ?: 'فاتورة ميتا' }} <small>{{ $i->paymentMethod?->name }} · سعر {{ rtrim(rtrim(number_format((float) $i->usd_rate, 4), '0'), '.') }}</small>@if($i->notes)<small>{{ $i->notes }}</small>@endif</span>
                    <span>{{ $usd($i->amount_usd) }}</span>
                    <span>{{ $i->fund_transfer_id ? $ils((float) $i->amount_usd * (float) $i->usd_rate) : '—' }}</span>
                </div>
            @empty
                <p class="cl-empty">ما في فواتير.</p>
            @endforelse
        </div>
    @else
        <form class="ad-limit" method="post" action="{{ route('cp.ads.limit') }}">
            @csrf
            <label>أقصى فرق مسموح مع لوحة ميتا <input name="limit" inputmode="decimal" dir="ltr" value="{{ rtrim(rtrim(number_format($limit, 2), '0'), '.') }}"> $</label>
            <button class="cp-btn cp-btn-ghost">حفظ</button>
        </form>
        <div class="ad-match">
            @forelse($match as $p => $r)
                @php($ok = $r['month']?->isApproved())
                @php($pr = $profit[$p] ?? null)
                <section class="ad-mcard {{ $ok ? 'ok' : '' }}">
                    <header><b>شهر {{ $ml($p) }}</b><span class="ad-badge">{{ $ok ? 'معتمد' : 'مسودة' }}</span></header>
                    <dl>
                        <div><dt>ضايل من الشهر الماضي</dt><dd>{{ $usd($r['prev']) }}</dd></div>
                        <div><dt>+ حملات الشهر</dt><dd>{{ $usd($r['spend']) }}</dd></div>
                        <div><dt>− فواتير مدفوعة فيه</dt><dd>{{ $usd($r['paid']) }}</dd></div>
                        <div class="ad-exp"><dt>المفروض يترحّل</dt><dd>{{ $usd($r['expected']) }}</dd></div>
                        @if($ok)
                            <div><dt>رصيد لوحة ميتا</dt><dd>{{ $usd($r['month']->meta_balance_usd) }}</dd></div>
                            @if($r['month']->settlement_usd !== null)<div><dt>تسوية</dt><dd>{{ $usd($r['month']->settlement_usd) }}</dd></div>@endif
                        @endif
                        @if($pr)
                            <div><dt>الربح</dt><dd class="{{ $pr['profit'] < 0 ? 'due' : 'credit' }}">{{ $ils($pr['profit']) }} @unless($pr['confirmed'])<small>تقديري</small>@endunless</dd></div>
                        @endif
                    </dl>
                    @unless($ok)
                        <form method="post" action="{{ route('cp.ads.approve', $p) }}" class="ad-approve">
                            @csrf
                            <label>رصيد لوحة ميتا آخر الشهر <input name="meta_balance" inputmode="decimal" dir="ltr" required value="{{ session('adsPeriod') === $p ? old('meta_balance') : '' }}" placeholder="{{ number_format($r['expected'], 2, '.', '') }}"></label>
                            <button class="cp-btn cp-btn-primary">اعتماد</button>
                            @if(session('adsPeriod') === $p && str_contains($errors->first('ads'), 'تخطّي'))
                                <button class="cp-btn cp-btn-ghost" name="skip" value="1">تخطّي الفرق واعتماد</button>
                            @endif
                        </form>
                    @endunless
                </section>
            @empty
                <p class="cl-empty">ما في شهور لسا.</p>
            @endforelse
        </div>
    @endif
</div>

<div class="cl-sheet" id="adCamp" hidden>
    <form class="cl-dialog" method="post" action="{{ route('cp.ads.campaigns.store') }}" id="adCampForm" role="dialog" aria-modal="true" aria-labelledby="adCampT">
        @csrf
        <input type="hidden" name="_method" id="adCampMethod" value="POST">
        <h2 id="adCampT">حملة جديدة</h2>
        <p class="ad-warn" id="adCampWarn" hidden>الشهر معتمد. الحفظ بيرجّعه مسودة وبتحتاج تعتمده من جديد.</p>
        <div class="cl-field"><label for="adClient">الزبون</label>
            <select id="adClient" name="client_id" class="cs-sel">
                <option value="">اختار…</option>
                @foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->directoryLabel() }}</option>@endforeach
                <option value="new">＋ زبون جديد</option>
            </select>
            <input id="adNewClient" name="new_client" placeholder="اسم الزبون الجديد" hidden>
        </div>
        <div class="cs-two">
            <div class="cl-field"><label for="adPeriod">الشهر</label>
                <select id="adPeriod" name="period" class="cs-sel">@foreach($monthOpts as $p)<option value="{{ $p }}" @selected($p === $thisMonth)>{{ $ml($p) }}</option>@endforeach</select></div>
            <div class="cl-field"><label>المنصة</label>
                <div class="ad-toggles">
                    <label><input type="checkbox" name="on_facebook" value="1" id="adFb" checked> {!! $fb !!} فيس</label>
                    <label><input type="checkbox" name="on_instagram" value="1" id="adIg"> {!! $ig !!} انستا</label>
                </div></div>
        </div>
        <div class="cs-two">
            <div class="cl-field"><label for="adSpend">الصرف $</label><input id="adSpend" name="spend_usd" inputmode="decimal" dir="ltr" required placeholder="0.00"></div>
            <div class="cl-field"><label for="adRate">سعر البيع</label><input id="adRate" name="sell_rate" inputmode="decimal" dir="ltr" required value="5"></div>
        </div>
        <p class="cs-hint" id="adTotal"></p>
        <div class="cl-field"><label for="adNotes">ملاحظة</label><input id="adNotes" name="notes"></div>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary">حفظ</button>
            <button type="button" class="cp-btn cp-btn-ghost" data-close>إلغاء</button>
        </div>
    </form>
</div>

<div class="cl-sheet" id="adInv" hidden>
    <form class="cl-dialog" method="post" action="{{ route('cp.ads.invoices.store') }}" role="dialog" aria-modal="true" aria-labelledby="adInvT">
        @csrf
        <h2 id="adInvT">فاتورة ميتا</h2>
        <div class="cs-two">
            <div class="cl-field"><label for="adInvDate">التاريخ</label><input id="adInvDate" type="date" name="invoice_date" value="{{ now()->toDateString() }}" required></div>
            <div class="cl-field"><label for="adInvAmt">المبلغ $</label><input id="adInvAmt" name="amount_usd" inputmode="decimal" dir="ltr" required placeholder="0.00"></div>
        </div>
        <div class="cs-two">
            <div class="cl-field"><label for="adInvFund">الدرج</label>
                <select id="adInvFund" name="fund_id" class="cs-sel">@foreach($funds as $f)<option value="{{ $f->id }}" @selected($f->slug === 'business')>{{ $f->slug === 'family' ? 'شخصي' : ($f->slug === 'business' ? 'عمل' : $f->name) }}</option>@endforeach</select></div>
            <div class="cl-field"><label for="adInvMethod">انخصمت من</label>
                <select id="adInvMethod" name="payment_method_id" class="cs-sel">@foreach($methods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
        </div>
        <div class="cs-two">
            <div class="cl-field"><label for="adInvPaid">انخصمت بـ</label>
                <select id="adInvPaid" name="paid_in" class="cs-sel"><option value="ILS">شيكل (صرف عملة)</option><option value="USD">دولار</option></select></div>
            <div class="cl-field"><label for="adInvRate">سعر الدولار</label><input id="adInvRate" name="usd_rate" inputmode="decimal" dir="ltr" required value="{{ \App\Support\Rates::today()['USD'] ?? '' }}"></div>
        </div>
        <p class="cs-hint" id="adInvHint">بتنسجل صرف عملة بنفس الحساب (شيكل ← دولار)، وبعدها دفعة بالدولار لحساب ميتا.</p>
        <div class="cl-field"><label for="adInvRef">رقم الفاتورة</label><input id="adInvRef" name="meta_ref" dir="ltr"></div>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary">سجّل</button>
            <button type="button" class="cp-btn cp-btn-ghost" data-close>إلغاء</button>
        </div>
    </form>
</div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const rates = @json($lastRates);
    const upd = @json(url('cp/ads/campaigns'));
    const store = @json(route('cp.ads.campaigns.store'));
    const closeMenus = () => document.querySelectorAll('.cl-menu').forEach((m) => { m.hidden = true; });
    const total = () => { const s = parseFloat($('adSpend').value) || 0, r = parseFloat($('adRate').value) || 0; $('adTotal').textContent = s && r ? 'على الزبون: ' + (s * r).toFixed(2) + ' ₪' : ''; };
    const openCamp = (d) => {
        $('adCampForm').action = d ? upd + '/' + d.id : store;
        $('adCampMethod').value = d ? 'PUT' : 'POST';
        $('adCampT').textContent = d ? 'تعديل حملة' : 'حملة جديدة';
        $('adCampWarn').hidden = !(d && d.approved);
        $('adClient').value = d ? d.client_id : '';
        $('adNewClient').hidden = true; $('adNewClient').value = '';
        if (d) { $('adPeriod').value = d.period; $('adFb').checked = d.fb; $('adIg').checked = d.ig; $('adSpend').value = d.spend; $('adRate').value = d.rate; $('adNotes').value = d.notes || ''; }
        else { $('adSpend').value = ''; $('adRate').value = 5; $('adNotes').value = ''; $('adFb').checked = true; $('adIg').checked = false; }
        total(); $('adCamp').hidden = false; setTimeout(() => $('adClient').focus(), 30);
    };
    document.addEventListener('click', (e) => {
        const more = e.target.closest('.ad-row .cl-more');
        if (more) { e.stopPropagation(); const m = more.nextElementSibling; const open = m.hidden; closeMenus(); m.hidden = !open; return; }
        if (e.target.closest('[data-new-campaign]')) { openCamp(null); return; }
        const ed = e.target.closest('[data-edit]');
        if (ed) { closeMenus(); openCamp(JSON.parse(ed.dataset.edit)); return; }
        const op = e.target.closest('[data-open]');
        if (op) { $(op.dataset.open).hidden = false; return; }
        if (e.target.closest('[data-close]') || e.target.classList.contains('cl-sheet')) { e.target.closest('.cl-sheet').hidden = true; return; }
        if (!e.target.closest('.cl-menu')) closeMenus();
    });
    document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    $('adClient').addEventListener('change', () => {
        const v = $('adClient').value, isNew = v === 'new';
        $('adNewClient').hidden = !isNew; $('adNewClient').required = isNew;
        if (isNew) { $('adNewClient').focus(); $('adRate').value = 5; } else if (v && rates[v]) { $('adRate').value = rates[v]; }
        total();
    });
    $('adCampForm').addEventListener('submit', () => { if ($('adClient').value === 'new') $('adClient').value = ''; });
    ['adSpend', 'adRate'].forEach((id) => $(id).addEventListener('input', total));
    $('adInvPaid').addEventListener('change', () => { $('adInvHint').textContent = $('adInvPaid').value === 'ILS' ? 'بتنسجل صرف عملة بنفس الحساب (شيكل ← دولار)، وبعدها دفعة بالدولار لحساب ميتا.' : 'بتنسجل دفعة بالدولار من رصيد الدولار بالحساب.'; });
})();
</script>
@endsection
