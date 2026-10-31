@extends('cp.layout')
@section('title', 'خدمات الشهر')
@section('content')
<div class="cl-page st-page">
    <div class="cl-top">
        <h1 class="cl-h">خدمات شهر {{ $untilLabel }} جاهزة للتأكيد</h1>
    </div>
    <p class="st-note">علّم الأشهر اللي بدك تسجّلها، وعدّل المبلغ إذا لزم. أول وآخر شهر محسوبين حسب الأيام. ولا إشي بينسجّل لحاله.</p>

    @if($rows->isEmpty())
        <section class="st-card"><p class="st-empty">كل الاشتراكات مسجّلة لغاية شهر {{ $untilLabel }}.</p></section>
    @else
        <form method="post" action="{{ route('cp.subscriptions.confirm.store') }}" id="subForm">
            @csrf
            <input type="hidden" name="month" value="{{ $until }}">
            @foreach($rows->groupBy('period') as $period => $items)
                <section class="st-card">
                    <header class="st-card-h"><h2>شهر {{ $items->first()['periodLabel'] }}</h2><span class="st-count">{{ $items->count() }} اشتراك</span></header>
                    @foreach($items as $r)
                        <label class="st-row sub-row">
                            <input type="checkbox" name="items[{{ $r['key'] }}][on]" value="1" checked class="sub-on">
                            <div class="st-name">
                                <b>{{ $r['client']->directoryLabel() }}</b>
                                <span>{{ $r['title'] }}@if($r['amount'] !== $r['full']) · السعر الكامل {{ number_format((float) $r['full'], 2) }} ₪@endif</span>
                            </div>
                            <span class="sub-amt"><input name="items[{{ $r['key'] }}][amount]" value="{{ $r['amount'] }}" inputmode="decimal" dir="ltr" aria-label="المبلغ"> ₪</span>
                        </label>
                    @endforeach
                </section>
            @endforeach
            <div class="cl-actions">
                <button type="submit" class="cp-btn cp-btn-primary">تأكيد المعلّم</button>
                <a href="{{ route('cp.clients.index') }}" class="cp-btn cp-btn-ghost">بعدين</a>
            </div>
        </form>
    @endif
</div>
@endsection
