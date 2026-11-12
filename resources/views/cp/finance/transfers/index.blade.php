@extends('cp.layout')
@section('title', 'التحويلات')
@section('content')
@php
    // صفحة التحويلات بستايل الأدراج (12/11/2026): نفس نافذة التحويل، الحركات مجمّعة باليوم، والإلغاء من «⋯».
    $base = route('cp.transfers.index', array_filter(['q' => request('q')]));
@endphp
<div class="cl-page bl-page bl-tpage">
    <div class="bl-top">
        <form class="cl-search" role="search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="دوّر بالملاحظات" aria-label="بحث">
            @if($from)<input type="hidden" name="from" value="{{ $from }}">@endif
            @if($to)<input type="hidden" name="to" value="{{ $to }}">@endif
        </form>
        @include('cp.partials.transfer-wizard', ['wizard' => $wizard])
    </div>

    <section class="bl-sec bl-led">
        <div class="bl-sh bl-ledh">
            <h2>التحويلات</h2>
            <div class="bl-ledf">@include('cp.partials.period-filter')</div>
        </div>
        <div data-soft="rows">
        @if($transfers->count())
            <div class="bl-lrow bl-trow bl-lhead"><span>التحويل</span><span>المبلغ</span><span></span></div>
            @php $lastDay = null; @endphp
            @foreach($transfers as $t)
                @php
                    $day = $t->transfer_date->toDateString();
                    $toCur = $t->toCurrency ?? $t->currency;
                    $same = $t->from_payment_method_id === $t->to_payment_method_id;
                    $title = $same
                        ? 'تحويل عملات · '.$t->fromMethod->name
                        : $t->fromMethod->name.' ← '.$t->toMethod->name;
                    $sub = [];
                    if ($t->isFx()) { $sub[] = 'السعر '.rtrim(rtrim(number_format((float) $t->exchange_rate, 4), '0'), '.'); }
                    if (\App\Support\Money::isPositive($t->fee_amount)) { $sub[] = 'رسوم '.$t->currency->format($t->fee_amount); }
                    if ($t->notes) { $sub[] = $t->notes; }
                @endphp
                @if($day !== $lastDay)
                    <div class="bl-lday">{{ day_label($t->transfer_date) }}</div>
                    @php $lastDay = $day; @endphp
                @endif
                <div class="bl-lrow bl-trow {{ $t->is_reversed ? 'off' : '' }}">
                    <span class="bl-ldesc">
                        <span class="bl-tic">@include('cp.partials.bs-icon', ['name' => $same ? 'exchange' : 'transfer', 'size' => 16])</span>
                        <span><b>{{ $title }}</b>@if($t->is_reversed)<em class="bl-off">ملغي</em>@endif
                        @if($sub)<small>{{ implode(' · ', $sub) }}</small>@endif</span>
                    </span>
                    <span class="bl-lamt" dir="ltr">{{ $t->currency->format($t->amount) }}@if($t->isFx())<small>→ {{ $toCur->format($t->to_amount) }}</small>@endif</span>
                    <span class="cl-mwrap">
                        @unless($t->is_reversed)
                            <button type="button" class="cl-more" aria-label="خيارات" data-more>⋯</button>
                            <div class="cl-menu" hidden>
                                <form method="post" action="{{ route('cp.transfers.reverse', $t) }}" data-confirm="بدك تلغي التحويل؟ المبلغ برجع للدرج اللي طلع منه.">@csrf
                                    <button class="danger">إلغاء التحويل</button>
                                </form>
                            </div>
                        @endunless
                    </span>
                </div>
            @endforeach
        @else
            <p class="cl-empty">{{ request('q') || $from || $to ? 'ما في تحويلات بهالبحث أو الفترة.' : 'لسا ما في تحويلات. اضغط «تحويل» فوق.' }}</p>
        @endif
        </div>
    </section>
    <div data-soft="pages">@include('cp.partials.bl-pages', ['p' => $transfers])</div>
</div>
@include('cp.partials.period-filter-go', ['base' => $base])
<script>
document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-more]');
    document.querySelectorAll('.bl-trow .cl-menu').forEach((m) => { if (!b || m !== b.nextElementSibling) m.hidden = true; });
    if (b) { e.stopPropagation(); b.nextElementSibling.hidden = !b.nextElementSibling.hidden; }
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.querySelectorAll('.bl-trow .cl-menu').forEach((m) => { m.hidden = true; }); });
</script>
@endsection
