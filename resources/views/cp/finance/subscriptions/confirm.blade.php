@extends('cp.layout')
@section('title', 'خدمات الشهر')
@section('content')
@php
    $current = now()->format('Y-m');
    $prevEnd = now()->startOfMonth()->subDay();
@endphp
<div class="cl-page st-page">
    <div class="cl-top">
        <h1 class="cl-h">فواتير الاشتراكات الشهرية</h1>
    </div>
    <p class="st-note">الشهر اللي انتهى بتطلع فاتورته حسب الأيام اللي أخد فيها الزبون الخدمة، والشهر الجديد إله فاتورة جديدة لحاله. كل فاتورة بتنسجّل حركة منفصلة على الزبون، ولا إشي بينسجّل لحاله.</p>

    @if($rows->isEmpty())
        <section class="st-card"><p class="st-empty">ما في فواتير اشتراكات جاهزة لغاية شهر {{ $untilLabel }}. الشهر اللي بلّش بنصّه بتطلع فاتورته أول الشهر الجاي.</p></section>
    @else
        <form method="post" action="{{ route('cp.subscriptions.confirm.store') }}" id="subForm">
            @csrf
            <input type="hidden" name="month" value="{{ $until }}">
            @foreach($rows->groupBy('period') as $period => $items)
                @php($closed = $items->first()['closed'])
                <section class="st-card">
                    <header class="st-card-h">
                        <h2>شهر {{ $items->first()['periodLabel'] }}</h2>
                        <span class="st-count">{{ $closed ? 'انتهى · الفاتورة حسب أيام الخدمة' : ($period === $current ? 'شهر جديد · فاتورة جديدة' : 'شهر جاي') }} · {{ $items->count() }} اشتراك</span>
                    </header>
                    @foreach($items as $r)
                        <div class="st-row sub-row">
                            <input type="checkbox" name="items[{{ $r['key'] }}][on]" value="1" checked class="sub-on" id="sub-{{ $loop->parent->index }}-{{ $loop->index }}" aria-label="سجّل فاتورة {{ $r['client']->directoryLabel() }}">
                            <label class="st-name" for="sub-{{ $loop->parent->index }}-{{ $loop->index }}">
                                <b>{{ $r['client']->directoryLabel() }}</b>
                                <span>{{ $r['title'] }}
                                    @if($r['partial'])
                                        · <i class="sub-days">{{ $r['days'] }} يوم من {{ $r['inMonth'] }}</i> · السعر الكامل {{ number_format((float) $r['full'], 2) }} ₪
                                    @else
                                        · شهر كامل
                                    @endif
                                </span>
                            </label>
                            @if(! $closed && ! $r['sub']->ends_on && $r['sub']->starts_on->lte($prevEnd))
                                <button type="button" class="sub-stop" data-stop="{{ route('cp.subscriptions.stop', $r['sub']) }}" data-what="{{ $r['client']->directoryLabel() }} · {{ $r['title'] }}" title="الزبون ما كمّل: وقّف الاشتراك بآخر يوم بالشهر الماضي">ما كمّل</button>
                            @endif
                            <span class="sub-amt"><input name="items[{{ $r['key'] }}][amount]" value="{{ $r['amount'] }}" inputmode="decimal" dir="ltr" aria-label="المبلغ"> ₪</span>
                        </div>
                    @endforeach
                </section>
            @endforeach
            <div class="cl-actions">
                <button type="submit" class="cp-btn cp-btn-primary">سجّل الفواتير المعلّمة</button>
                <a href="{{ route('cp.clients.index') }}" class="cp-btn cp-btn-ghost">بعدين</a>
            </div>
        </form>
        <form method="post" id="subStopForm" hidden>
            @csrf
            <input type="hidden" name="ends_on" value="{{ $prevEnd->format('d/m/Y') }}">
            <input type="hidden" name="return" value="confirm">
        </form>
        <script>
        document.querySelectorAll('.sub-stop').forEach((b) => b.addEventListener('click', () => {
            const msg = `إيقاف اشتراك «${b.dataset.what}» بتاريخ {{ $prevEnd->format('d/m/Y') }}؟ ما بتنعمل فاتورة للشهر الجديد.`;
            const ask = window.cpConfirm ? window.cpConfirm(msg) : Promise.resolve(window.confirm(msg));
            ask.then((ok) => { if (ok) { const f = document.getElementById('subStopForm'); f.action = b.dataset.stop; f.submit(); } });
        }));
        </script>
    @endif
</div>
@endsection
