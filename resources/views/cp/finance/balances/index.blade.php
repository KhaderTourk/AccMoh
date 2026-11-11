@extends('cp.layout')
@section('title', 'الأدراج والأرصدة')
@section('content')
@php
    $n = fn ($v) => ($v < 0 ? '−' : '').number_format(abs((float) $v), 2).' ₪';
    $tabs = $funds->mapWithKeys(fn ($f) => [$f->slug => $f->slug === 'family' ? 'شخصي' : ($f->slug === 'business' ? 'عمل' : $f->name)])->all() + ['all' => 'الكل'];
    $fx = collect($rates)->except('ILS');
@endphp
<div class="cl-page bl-page">
    <div class="cl-top">
        <h1 class="cl-h">الأدراج والأرصدة</h1>
        <div class="cl-sp"></div>
        <a href="{{ route('cp.transfers.create') }}" class="cp-btn cp-btn-primary"><span class="material-symbols-outlined">swap_horiz</span> تحويل</a>
    </div>

    <div class="bl-total">
        <span>المجموع بسعر اليوم</span>
        <b class="{{ $total < 0 ? 'due' : '' }}">{{ $n($total) }}</b>
        <button type="button" class="bl-rates" id="blRatesBtn">
            @forelse($fx as $code => $r)
                <span>{{ $code === 'USD' ? 'الدولار' : ($code === 'JOD' ? 'الدينار' : $code) }} {{ rtrim(rtrim(number_format($r, 4), '0'), '.') }}</span>
            @empty
                <span>حدّد سعر الدولار والدينار</span>
            @endforelse
            <span class="material-symbols-outlined">edit</span>
        </button>
    </div>
    <form class="bl-rates-form" id="blRatesForm" method="post" action="{{ route('cp.balances.rates') }}" hidden>
        @csrf
        @foreach($currencies->where('code', '!=', 'ILS') as $c)
            <label>{{ $c->name }} بالشيكل <input name="rates[{{ $c->code }}]" inputmode="decimal" dir="ltr" value="{{ $rates[$c->code] ?? '' }}" placeholder="مثلاً 3.70"></label>
        @endforeach
        <button class="cp-btn cp-btn-primary">حفظ</button>
        <small>السعر للمجاميع بس. كل حركة محفوظة بسعرها وقت التسجيل.</small>
    </form>

    <div class="bl-grid">
        @foreach($cards as $card)
            <div class="bl-card">
                <a class="bl-main" href="{{ route('cp.balances.show', ['method' => $card['method']->id]) }}">
                    <span class="bl-name">{{ $card['method']->name }}</span>
                    @if(!count($card['currencies']))
                        <b class="bl-empty">فاضي</b>
                    @else
                        <b class="{{ $card['ils'] < 0 ? 'due' : '' }}">{{ $n($card['ils']) }}</b>
                        <span class="bl-curs">
                            @foreach($card['currencies'] as $c)<i class="{{ $c['negative'] ? 'due' : '' }}">{{ $c['label'] }}</i>@endforeach
                        </span>
                    @endif
                </a>
                <button type="button" class="cl-more" aria-label="خيارات {{ $card['method']->name }}">⋯</button>
                <div class="cl-menu" hidden>
                    <button type="button" data-settle="{{ $card['method']->id }}" data-name="{{ $card['method']->name }}">تسوية رصيد</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="cl-sheet" id="blSheet" hidden>
    <form class="cl-dialog" method="post" action="{{ route('cp.balances.settle') }}" role="dialog" aria-modal="true" aria-labelledby="blSheetT">
        @csrf
        <h2 id="blSheetT">تسوية رصيد</h2>
        <input type="hidden" name="payment_method_id" id="blMethod">
        <div class="cs-two">
            <div class="cl-field"><label for="blCur">العملة</label>
                <select id="blCur" name="currency_id" class="cs-sel">@foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }} {{ $c->symbol }}</option>@endforeach</select></div>
        </div>
        <div class="cl-field"><label for="blActual">الموجود فعلياً</label><input id="blActual" name="actual" inputmode="decimal" dir="ltr" placeholder="0.00" required>
            <small class="cs-hint">بتنسجل حركة «تسوية رصيد» بالفرق بينه وبين اللي بالنظام.</small></div>
        <div class="cl-field"><label for="blNotes">ليش الفرق؟</label><textarea id="blNotes" name="notes" rows="2" required placeholder="ملاحظة إجبارية"></textarea></div>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary">سجّل التسوية</button>
            <button type="button" class="cp-btn cp-btn-ghost" id="blCancel">إلغاء</button>
        </div>
    </form>
</div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const close = () => document.querySelectorAll('.cl-menu').forEach((m) => { m.hidden = true; });
    $('blRatesBtn').addEventListener('click', () => { $('blRatesForm').hidden = !$('blRatesForm').hidden; });
    document.addEventListener('click', (e) => {
        const more = e.target.closest('.bl-card .cl-more');
        if (more) { e.stopPropagation(); const m = more.nextElementSibling; const open = m.hidden; close(); m.hidden = !open; return; }
        const s = e.target.closest('[data-settle]');
        if (s) { close(); $('blMethod').value = s.dataset.settle; $('blSheetT').textContent = 'تسوية رصيد · ' + s.dataset.name; $('blSheet').hidden = false; setTimeout(() => $('blActual').focus(), 30); return; }
        if (!e.target.closest('.cl-menu')) close();
    });
    $('blCancel').addEventListener('click', () => { $('blSheet').hidden = true; });
    $('blSheet').addEventListener('click', (e) => { if (e.target.id === 'blSheet') $('blSheet').hidden = true; });
})();
</script>
@endsection
