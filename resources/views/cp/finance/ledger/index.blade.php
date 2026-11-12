@extends('cp.layout')
@section('title', 'سجل الحركات')
@section('content')
@php
    // سجل الحركات بستايل الأدراج (12/11/2026): شريط صافي الفترة، فلاتر بقوائم البراند (بدون قوائم المتصفح)، والحركات مجمّعة باليوم.
    $keep = fn (array $over) => route('cp.ledger.index', array_filter(array_merge(request()->only(['q', 'transaction_type', 'payment_method_id', 'currency_id']), ['from' => $from, 'to' => $to], $over), fn ($v) => $v !== null && $v !== ''));
    $typeOn = collect($types)->first(fn ($t) => $t->value === request('transaction_type'));
    $methodOn = $paymentMethods->firstWhere('id', (int) request('payment_method_id'));
    $curOn = $currencies->firstWhere('id', (int) request('currency_id'));
    $cName = fn ($c) => \App\Support\DrawerPlaces::curName($c->code, $c->name);
    $signed = fn ($c, $v) => ((float) $v < 0 ? '−' : ((float) $v > 0 ? '+' : '')).$c->format(abs((float) $v));
@endphp
<div class="cl-page bl-page bl-gpage">
    <div class="bl-top">
        <form class="cl-search" role="search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="دوّر بالبيان أو الملاحظات" aria-label="بحث">
            @foreach(['transaction_type', 'payment_method_id', 'currency_id'] as $k)@if(filled(request($k)))<input type="hidden" name="{{ $k }}" value="{{ request($k) }}">@endif @endforeach
            @if($from)<input type="hidden" name="from" value="{{ $from }}">@endif
            @if($to)<input type="hidden" name="to" value="{{ $to }}">@endif
        </form>
    </div>

    @if($totals->isNotEmpty())
        <div class="bl-card bl-strip bl-gsum">
            @foreach($totals as $row)
                <div class="bl-gtot">
                    <span>صافي {{ $cName($row['currency']) }}</span>
                    <b class="bl-money {{ (float) $row['total'] < 0 ? 'due' : 'credit' }}" dir="ltr">{{ $signed($row['currency'], $row['total']) }}</b>
                    <small><span>دخل <i dir="ltr">{{ $row['currency']->format((float) $row['in']) }}</i></span><span>طلع <i dir="ltr">{{ $row['currency']->format(abs((float) $row['out'])) }}</i></span></small>
                </div>
            @endforeach
        </div>
    @endif

    <section class="bl-sec bl-led">
        <div class="bl-sh bl-ledh">
            <h2>الحركات</h2>
            <div class="bl-ledf">
                <div class="bl-fm">
                    <button type="button" class="cs-range-btn {{ $typeOn ? 'on' : '' }}" data-fm>{{ $typeOn ? $typeOn->label() : 'كل الأنواع' }} @include('cp.partials.bs-icon', ['name' => 'chevron', 'size' => 12])</button>
                    <div class="cl-menu" hidden>
                        <a href="{{ $keep(['transaction_type' => null]) }}" class="{{ $typeOn ? '' : 'on' }}">كل الأنواع</a>
                        @foreach($types as $t)<a href="{{ $keep(['transaction_type' => $t->value]) }}" class="{{ $typeOn === $t ? 'on' : '' }}">{{ $t->label() }}</a>@endforeach
                    </div>
                </div>
                <div class="bl-fm">
                    <button type="button" class="cs-range-btn {{ $methodOn ? 'on' : '' }}" data-fm>{{ $methodOn ? $methodOn->name : 'كل الأدراج' }} @include('cp.partials.bs-icon', ['name' => 'chevron', 'size' => 12])</button>
                    <div class="cl-menu" hidden>
                        <a href="{{ $keep(['payment_method_id' => null]) }}" class="{{ $methodOn ? '' : 'on' }}">كل الأدراج</a>
                        @foreach($paymentMethods as $m)<a href="{{ $keep(['payment_method_id' => $m->id]) }}" class="{{ $methodOn?->id === $m->id ? 'on' : '' }}">{{ $m->name }}</a>@endforeach
                    </div>
                </div>
                <div class="bl-seg2">
                    <a href="{{ $keep(['currency_id' => null]) }}" class="{{ $curOn ? '' : 'on' }}">الكل</a>
                    @foreach($currencies as $c)<a href="{{ $keep(['currency_id' => $c->id]) }}" class="{{ $curOn?->id === $c->id ? 'on' : '' }}">{{ $cName($c) }}</a>@endforeach
                </div>
                @include('cp.partials.period-filter')
            </div>
        </div>

        @if($entries->count())
            <div class="bl-lrow bl-grow bl-lhead"><span>البيان</span><span>الدرج</span><span>المبلغ</span></div>
            @php $lastDay = null; @endphp
            @foreach($entries as $e)
                @php
                    $day = $e->occurred_on->toDateString();
                    $in = (float) $e->amount > 0;
                    $party = $e->counterpartyLabel();
                    $text = \App\Support\LedgerText::for($e);
                    $meta = array_filter([str_contains($text, $e->transaction_type->label()) ? null : $e->transaction_type->label(), $party && ! str_contains($text, $party) ? $party : null]);
                @endphp
                @if($day !== $lastDay)
                    <div class="bl-lday">{{ day_label($e->occurred_on) }}</div>
                    @php $lastDay = $day; @endphp
                @endif
                <div class="bl-lrow bl-grow">
                    <span class="bl-ldesc">
                        <b>{{ $text }}</b>@if($e->is_reversal)<em class="bl-off">إلغاء</em>@endif
                        <small>{{ implode(' · ', array_merge($meta, array_filter([$e->notes, $e->recordedAt()]))) }} @includeWhen(($e->related instanceof \App\Models\CashPayment) && $e->related->party && $e->related->party->is_active === false, 'cp.partials.archived-badge')</small>
                    </span>
                    <span class="bl-gm">{{ $e->paymentMethod->name }}</span>
                    <span class="bl-lamt {{ $in ? 'in' : '' }}" dir="ltr">{{ $in ? '+' : '−' }}{{ $e->currency->format(abs((float) $e->amount)) }}</span>
                </div>
            @endforeach
        @else
            <p class="cl-empty">ما في حركات بهالفلاتر.</p>
        @endif
    </section>
    @include('cp.partials.bl-pages', ['p' => $entries])
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
