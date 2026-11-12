@extends('cp.layout')
@section('title', 'التقارير')
@section('content')
@php
    // التقارير بستايل الأدراج (12/11/2026): فلاتر بقوائم البراند وفلتر الفترة الموحّد، أقسام بنفس كروت الأدراج.
    // ألوان الرصيد: «عليه» (إلنا) زهري، «إله» (علينا) أخضر، والمسدّد عادي.
    $filterQuery = $filterQuery ?? \App\Support\DateRange::queryParams($from, $to);
    $keys = ['q', 'currency_id', 'payment_method_id', 'party_type', 'client_id', 'person_id'];
    $keep = fn (array $over) => route('cp.reports.index', array_filter(array_merge(request()->only($keys), ['from' => $from, 'to' => $to], $over), fn ($v) => $v !== null && $v !== ''));
    $biz = tenantBusinessEnabled();
    $pt = request('party_type');
    $show = fn ($t) => ! $pt || $pt === $t;
    $types = array_filter(['client' => $biz ? 'الزبائن' : null, 'person' => 'الأشخاص', 'worker' => $biz ? 'الموظفين' : null, 'supplier' => $biz ? 'الموردين' : null]);
    $methodOn = $paymentMethods->firstWhere('id', (int) request('payment_method_id'));
    $clientOn = $biz ? $clients->firstWhere('id', (int) request('client_id')) : null;
    $personOn = $persons->firstWhere('id', (int) request('person_id'));
    $curOn = $currencies->firstWhere('id', (int) request('currency_id'));
    $cName = fn ($c) => \App\Support\DrawerPlaces::curName($c->code, $c->name);
    $M = \App\Support\Money::class;
    // صف عملات: بيعرض بس العملات اللي فيها قيمة، وإذا كلهن صفر بيعرض أول عملة.
    $lines = function (array $vals) use ($snapshot) {
        $out = [];
        foreach ($snapshot['currencies'] as $c) {
            $v = (float) ($vals[$c->id] ?? 0);
            if (abs($v) >= 0.005) { $out[] = [$c, $v]; }
        }
        return $out ?: [[$snapshot['currencies']->first(), 0.0]];
    };
    $toArr = fn ($x) => is_array($x) ? $x : collect($x)->all();
@endphp
<div class="cl-page bl-page bl-rpage">
    <div class="bl-top">
        <form class="cl-search" role="search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="دوّر بالدفعات: الاسم أو الملاحظات" aria-label="بحث">
            @foreach(array_diff($keys, ['q']) as $k)@if(filled(request($k)))<input type="hidden" name="{{ $k }}" value="{{ request($k) }}">@endif @endforeach
            @if($from)<input type="hidden" name="from" value="{{ $from }}">@endif
            @if($to)<input type="hidden" name="to" value="{{ $to }}">@endif
        </form>
        <div class="bl-racts">
            @if($biz)<a href="{{ route('cp.reports.services', $filterQuery) }}" class="cp-btn cp-btn-ghost">دخل الخدمات</a>@endif
            <a href="{{ route('cp.reports.export-pdf', request()->only($keys) + $filterQuery) }}" class="cp-btn cp-btn-primary">تصدير PDF</a>
        </div>
    </div>

    <div class="bl-rfil">
        <div class="bl-fm">
            <button type="button" class="cs-range-btn {{ $pt ? 'on' : '' }}" data-fm>{{ $types[$pt] ?? 'كل الأطراف' }} @include('cp.partials.bs-icon', ['name' => 'chevron', 'size' => 12])</button>
            <div class="cl-menu" hidden>
                <a href="{{ $keep(['party_type' => null]) }}" class="{{ $pt ? '' : 'on' }}">كل الأطراف</a>
                @foreach($types as $k => $label)<a href="{{ $keep(['party_type' => $k]) }}" class="{{ $pt === $k ? 'on' : '' }}">{{ $label }}</a>@endforeach
            </div>
        </div>
        <div class="bl-fm">
            <button type="button" class="cs-range-btn {{ $methodOn ? 'on' : '' }}" data-fm>{{ $methodOn ? $methodOn->name : 'كل الأدراج' }} @include('cp.partials.bs-icon', ['name' => 'chevron', 'size' => 12])</button>
            <div class="cl-menu" hidden>
                <a href="{{ $keep(['payment_method_id' => null]) }}" class="{{ $methodOn ? '' : 'on' }}">كل الأدراج</a>
                @foreach($paymentMethods as $m)<a href="{{ $keep(['payment_method_id' => $m->id]) }}" class="{{ $methodOn?->id === $m->id ? 'on' : '' }}">{{ $m->name }}</a>@endforeach
            </div>
        </div>
        @if($biz && $clients->isNotEmpty())
        <div class="bl-fm">
            <button type="button" class="cs-range-btn {{ $clientOn ? 'on' : '' }}" data-fm>{{ $clientOn ? $clientOn->personName() : 'كل الزبائن' }} @include('cp.partials.bs-icon', ['name' => 'chevron', 'size' => 12])</button>
            <div class="cl-menu" hidden>
                <a href="{{ $keep(['client_id' => null]) }}" class="{{ $clientOn ? '' : 'on' }}">كل الزبائن</a>
                @foreach($clients as $c)<a href="{{ $keep(['client_id' => $c->id]) }}" class="{{ $clientOn?->id === $c->id ? 'on' : '' }}">{{ $c->personName() }}</a>@endforeach
            </div>
        </div>
        @endif
        @if($persons->isNotEmpty())
        <div class="bl-fm">
            <button type="button" class="cs-range-btn {{ $personOn ? 'on' : '' }}" data-fm>{{ $personOn ? $personOn->name : 'كل الأشخاص' }} @include('cp.partials.bs-icon', ['name' => 'chevron', 'size' => 12])</button>
            <div class="cl-menu" hidden>
                <a href="{{ $keep(['person_id' => null]) }}" class="{{ $personOn ? '' : 'on' }}">كل الأشخاص</a>
                @foreach($persons as $p)<a href="{{ $keep(['person_id' => $p->id]) }}" class="{{ $personOn?->id === $p->id ? 'on' : '' }}">{{ $p->name }}</a>@endforeach
            </div>
        </div>
        @endif
        <div class="bl-seg2">
            <a href="{{ $keep(['currency_id' => null]) }}" class="{{ $curOn ? '' : 'on' }}">الكل</a>
            @foreach($currencies as $c)<a href="{{ $keep(['currency_id' => $c->id]) }}" class="{{ $curOn?->id === $c->id ? 'on' : '' }}">{{ $cName($c) }}</a>@endforeach
        </div>
        @include('cp.partials.period-filter')
        <span class="bl-rmeta">{{ $periodLabel }} · وارد {{ $incomingCount }} · صادر {{ $outgoingCount }}</span>
    </div>

    {{-- الأرصدة الحالية: ما بتتأثر بالفترة --}}
    <section class="bl-sec">
        <div class="bl-sh"><div><h2>الأرصدة الحالية</h2><small>زي ما هي هلأ، ما بتتأثر بالفترة.</small></div></div>
        <div class="bl-rbox">
            <div><span>بالأدراج</span>@foreach($lines($toArr($snapshot['grand'])) as [$c, $v])<b class="bl-money" dir="ltr">{{ ($v < 0 ? '−' : '').$c->format(abs($v)) }}</b>@endforeach</div>
            @if($biz)
                <div><span>على الزبائن</span>@foreach($lines($toArr($receivables)) as [$c, $v])<b class="bl-money {{ $v > 0.004 ? 'due' : ($v < -0.004 ? 'credit' : '') }}" dir="ltr">{{ $c->format(abs($v)) }}</b>@endforeach</div>
                <div><span>للموظفين</span>@foreach($lines($toArr($workerPayables)) as [$c, $v])<b class="bl-money {{ $v > 0.004 ? 'credit' : ($v < -0.004 ? 'due' : '') }}" dir="ltr">{{ $c->format(abs($v)) }}</b>@endforeach</div>
                <div><span>للموردين</span>@foreach($lines($toArr($supplierPayables)) as [$c, $v])<b class="bl-money {{ $v > 0.004 ? 'credit' : ($v < -0.004 ? 'due' : '') }}" dir="ltr">{{ $c->format(abs($v)) }}</b>@endforeach</div>
            @endif
            <div><span>الأشخاص</span>@foreach($lines($toArr($personNet)) as [$c, $v])<b class="bl-money {{ $v > 0.004 ? 'due' : ($v < -0.004 ? 'credit' : '') }}" dir="ltr">{{ $c->format(abs($v)) }}</b>@endforeach</div>
        </div>
    </section>

    @if($biz && count($profitRows))
    <section class="bl-sec">
        <div class="bl-sh"><div><h2>أرباح الفترة</h2><small>{{ $periodLabel }}</small></div></div>
        <div class="bl-rprof">
            @foreach($profitRows as $row)
                @php $c = $row['currency']; @endphp
                <div class="bl-rpc">
                    <h3>{{ $cName($c) }}</h3>
                    <div class="bl-rbig">
                        <div><span>صافي الأرباح</span><b class="bl-money {{ $M::isNegative($row['net_profit']) ? 'due' : 'credit' }}" dir="ltr">{{ $c->format($row['net_profit']) }}</b><small>دفعات الزبائن − صادر العمل</small></div>
                        <div><span>إجمالي الأرباح</span><b class="bl-money {{ $M::isNegative($row['gross_profit']) ? 'due' : 'credit' }}" dir="ltr">{{ $c->format($row['gross_profit']) }}</b><small>سعر الخدمات − صادر العمل − المستحقات</small></div>
                    </div>
                    <dl class="bl-kv">
                        <div><dt>سعر الخدمات المقدّمة</dt><dd dir="ltr">{{ $c->format($row['client_billed']) }}</dd></div>
                        <div><dt>دفعات من الزبائن</dt><dd dir="ltr" class="in">{{ $c->format($row['payments']) }}</dd></div>
                        <div><dt>صادر العمل <small>موظفين {{ $c->format($row['worker_expenses']) }} · موردين {{ $c->format($row['supplier_expenses']) }}</small></dt><dd dir="ltr">{{ $c->format($row['work_expenses']) }}</dd></div>
                        <div><dt>على الزبائن</dt><dd dir="ltr" class="due">{{ $c->format($row['client_outstanding']) }}</dd></div>
                        <div><dt>للموظفين</dt><dd dir="ltr" class="in">{{ $c->format($row['worker_outstanding']) }}</dd></div>
                        <div><dt>للموردين</dt><dd dir="ltr" class="in">{{ $c->format($row['supplier_outstanding']) }}</dd></div>
                    </dl>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    @if($biz && $show('client'))
    <section class="bl-sec bl-rtab">
        <div class="bl-sh"><h2>الزبائن</h2></div>
        <div class="bl-rrow bl-lhead {{ !empty($hasOpening) ? 'w7' : '' }}"><span>الزبون</span>@if(!empty($hasOpening))<span>رصيد سابق</span>@endif<span>الخدمات</span><span>المدفوع</span><span>بضاعة أخدها</span><span>الباقي</span></div>
        @forelse($clientSummary as $row)
            @foreach($row['rows'] as $r)
                @php $c = $r['currency']; $due = (float) $r['due']; @endphp
                <div class="bl-rrow {{ !empty($hasOpening) ? 'w7' : '' }}">
                    <span class="bl-rn"><a href="{{ route('cp.clients.show', array_merge(['client' => $row['client']], \App\Support\DateRange::queryParams($from, $to))) }}">{{ $row['client']->personName() }}</a></span>
                    @if(!empty($hasOpening))<span data-l="رصيد سابق">@if($M::isNegative($r['opening']))عربون @endif<i dir="ltr">{{ $c->format($M::abs($r['opening'])) }}</i></span>@endif
                    <span dir="ltr" data-l="الخدمات">{{ $c->format($r['billed']) }}</span>
                    <span dir="ltr" data-l="المدفوع">{{ $c->format($r['paid']) }}</span>
                    <span dir="ltr" data-l="بضاعة">{{ $c->format($r['goods'] ?? '0.00') }}</span>
                    <b class="{{ $due > 0.004 ? 'due' : ($due < -0.004 ? 'credit' : '') }}">@if($due < -0.004)<small>عربون</small> @endif<span dir="ltr">{{ $c->format(abs($due)) }}</span></b>
                </div>
            @endforeach
        @empty
            <p class="cl-empty">ما في بيانات بهالفلاتر.</p>
        @endforelse
    </section>
    @endif

    @foreach([['worker', 'الموظفين', 'الموظف', $workerSummary ?? collect(), 'workers'], ['supplier', 'الموردين', 'المورد', $supplierSummary ?? collect(), 'suppliers']] as [$t, $title, $nameLabel, $summary, $routePrefix])
        @if($biz && $show($t))
        <section class="bl-sec bl-rtab">
            <div class="bl-sh"><h2>{{ $title }}</h2></div>
            <div class="bl-rrow bl-lhead"><span>{{ $nameLabel }}</span><span>مستحق إله</span><span>المدفوع</span><span>الباقي</span></div>
            @forelse($summary as $row)
                @foreach($row['rows'] as $r)
                    @php $c = $r['currency']; $due = (float) $r['due']; @endphp
                    <div class="bl-rrow">
                        <span class="bl-rn"><a href="{{ route('cp.'.$routePrefix.'.show', $row['vendor']) }}">{{ $row['vendor']->name }}</a></span>
                        <span dir="ltr" data-l="مستحق">{{ $c->format($r['billed']) }}</span>
                        <span dir="ltr" data-l="المدفوع">{{ $c->format($r['paid']) }}</span>
                        <b class="{{ $due > 0.004 ? 'credit' : ($due < -0.004 ? 'due' : '') }}">@if($due < -0.004)<small>مقدّماً</small> @endif<span dir="ltr">{{ $c->format(abs($due)) }}</span></b>
                    </div>
                @endforeach
            @empty
                <p class="cl-empty">ما في بيانات بهالفلاتر.</p>
            @endforelse
        </section>
        @endif
    @endforeach

    @if($show('person'))
    <section class="bl-sec bl-rtab">
        <div class="bl-sh"><h2>الأشخاص</h2></div>
        <div class="bl-rrow bl-lhead"><span>الشخص</span><span>وارد</span><span>صادر</span><span>الموقف</span></div>
        @forelse($personSummary as $row)
            @foreach($row['rows'] as $r)
                @php $c = $r['currency']; $net = (float) $r['net']; @endphp
                <div class="bl-rrow">
                    <span class="bl-rn">{{ $row['member']->name }}</span>
                    <span dir="ltr" data-l="وارد">{{ $c->format($r['in']) }}</span>
                    <span dir="ltr" data-l="صادر">{{ $c->format($r['out']) }}</span>
                    <b dir="ltr" class="{{ $net > 0.004 ? 'due' : ($net < -0.004 ? 'credit' : '') }}">{{ $c->format(abs($net)) }}</b>
                </div>
            @endforeach
        @empty
            <p class="cl-empty">ما في بيانات بهالفلاتر.</p>
        @endforelse
    </section>
    @endif

    <div class="bl-rpay">
        @foreach([['دخل', $incoming, $incomingCount, $incomingTotals, true], ['طلع', $outgoing, $outgoingCount, $outgoingTotals, false]] as [$title, $list, $count, $totals, $in])
        <section class="bl-sec bl-led">
            <div class="bl-sh"><div><h2>{{ $title }} ({{ $count }})</h2>@if($totals->isNotEmpty())<small dir="ltr" class="{{ $in ? 'in' : '' }}">@foreach($totals as $total){{ $total['formatted'] }}@if(! $loop->last) · @endif @endforeach</small>@endif</div></div>
            <div class="bl-rscroll">
                @php $lastDay = null; @endphp
                @forelse($list as $p)
                    @php $day = $p->occurred_on->toDateString(); @endphp
                    @if($day !== $lastDay)<div class="bl-lday">{{ day_label($p->occurred_on) }}</div>@php $lastDay = $day; @endphp @endif
                    <div class="bl-lrow bl-prow">
                        <span class="bl-ldesc"><a href="{{ route('cp.payments.show', $p) }}">{{ $p->name }}</a><small>{{ implode(' · ', array_filter([$p->paymentMethod?->name, $p->notes])) }}</small></span>
                        <span class="bl-lamt {{ $in ? 'in' : '' }}" dir="ltr">{{ $in ? '+' : '−' }}{{ $p->currency->format($p->amount) }}</span>
                    </div>
                @empty
                    <p class="cl-empty">ما في دفعات بهالفلاتر.</p>
                @endforelse
                @if($count > $list->count())<p class="bl-rmore">ظاهر {{ $list->count() }} من {{ $count }}. ملف الـ PDF فيه الكل.</p>@endif
            </div>
        </section>
        @endforeach
    </div>
</div>
@include('cp.partials.period-filter-go', ['base' => $keep(['from' => null, 'to' => null])])
<script>
document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-fm]');
    document.querySelectorAll('.bl-fm .cl-menu').forEach((m) => { if (!b || m !== b.nextElementSibling) m.hidden = true; });
    if (b) { e.stopPropagation(); b.nextElementSibling.hidden = !b.nextElementSibling.hidden; }
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.querySelectorAll('.bl-fm .cl-menu').forEach((m) => { m.hidden = true; }); });
</script>
@endsection
