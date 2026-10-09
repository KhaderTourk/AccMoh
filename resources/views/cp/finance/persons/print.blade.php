@extends('cp.print')
@section('content')
@if(filled($member->notes))
<div class="card">
    <div class="kpi-label">ملاحظات</div>
    <div class="sub" style="white-space: pre-line;">{{ $member->notes }}</div>
</div>
@endif
<h2>ملخص الحساب</h2>
<table class="kpis">
    <tr>
    @foreach($currencies as $c)
        @php
            $in = $member->incomingAmount($c->id);
            $out = $member->outgoingAmount($c->id);
            $goods = $member->goodsTakenAmount($c->id);
            $memos = $member->memoDelta($c->id);
            $net = $member->netAmount($c->id);
        @endphp
        @if(!\App\Support\Money::isZero($in) || !\App\Support\Money::isZero($out) || !\App\Support\Money::isZero($goods) || !\App\Support\Money::isZero($memos))
        <td>
            <div class="kpi-label">{{ $c->name }}</div>
            <div class="sub">رصيد مرحّل: {{ $c->format($memos) }}</div>
            <div class="sub">وارد: {{ $c->format($in) }}</div>
            <div class="sub">صادر: {{ $c->format($out) }}</div>
            <div class="sub">خصم/أخرى: {{ $c->format($goods) }}</div>
            <div class="kpi-value {{ \App\Support\Money::isNegative($net) ? 'neg' : '' }}">الموقف {{ $c->format($net) }}{{ \App\Support\Money::isPositive($net) ? ' عليه' : (\App\Support\Money::isNegative($net) ? ' له' : '') }}</div>
        </td>
        @endif
    @endforeach
    </tr>
</table>

<h2>رصيد مرحّل</h2>
<table class="data">
    <thead><tr><th>البيان</th><th>الاتجاه</th><th>المبلغ</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($member->balanceMemos as $memo)
        <tr>
            <td>{{ $memo->statementTitle() }}@if(filled($memo->notes))<div class="sub" style="white-space: pre-line;">{{ $memo->notes }}</div>@endif</td>
            <td>{{ $memo->direction->label() }}</td>
            <td>{{ $memo->currency->format($memo->amount) }}</td>
            <td>{{ format_date($memo->memo_date) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">لا يوجد رصيد مرحّل.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>خصم/أخرى</h2>
<table class="data">
    <thead><tr><th>التصنيف</th><th>السعر</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($member->goodsTakes as $take)
        <tr>
            <td>{{ $take->title }}</td>
            <td>{{ $take->currency->format($take->amount) }}</td>
            <td>{{ format_date($take->taken_on) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا توجد سجلات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>الدفعات</h2>
<table class="data">
    <thead><tr><th>التاريخ</th><th>النوع</th><th>الدرج</th><th>الطريقة</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($member->cashPayments as $p)
        <tr>
            <td>{{ format_date($p->occurred_on) }}</td>
            <td>{{ $p->direction->label() }}{{ $p->is_reversed ? ' (ملغاة)' : '' }}</td>
            <td>{{ $p->fund->name }}</td>
            <td>{{ $p->paymentMethod->name }}</td>
            <td class="amount">
                {{ $p->currency->format($p->amount) }}
                @if($p->isFx())
                    <div class="sub">{{ $p->fxCurrency?->format($p->source_amount) }} × {{ $p->formattedExchangeRate() }}</div>
                @endif
                @if(filled($p->notes))
                    <div class="sub" style="white-space: pre-line;">{{ $p->notes }}</div>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="empty">لا توجد دفعات.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
