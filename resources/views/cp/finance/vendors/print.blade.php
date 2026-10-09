@extends('cp.print')
@section('content')
@if(filled($vendor->notes))
<div class="card">
    <div class="kpi-label">ملاحظات</div>
    <div class="sub" style="white-space: pre-line;">{{ $vendor->notes }}</div>
</div>
@endif
<h2>ملخص الحساب</h2>
<table class="kpis">
    <tr>
    @foreach($currencies as $currency)
        @php
            $billed = $vendor->billedAmount($currency->id);
            $paid = $vendor->paidAmount($currency->id);
            $goods = $vendor->goodsTakenAmount($currency->id);
            $memos = $vendor->memoDelta($currency->id);
            $due = $vendor->outstandingAmount($currency->id);
        @endphp
        @if(!\App\Support\Money::isZero($billed) || !\App\Support\Money::isZero($paid) || !\App\Support\Money::isZero($goods) || !\App\Support\Money::isZero($memos))
        <td>
            <div class="kpi-label">{{ $currency->name }}</div>
            <div class="sub">{{ $type->billedLabel() }}: {{ $currency->format($billed) }}</div>
            <div class="sub">المدفوع: {{ $currency->format($paid) }}</div>
            <div class="sub">خصم/أخرى: {{ $currency->format($goods) }}</div>
            <div class="sub">رصيد مرحّل: {{ $currency->format($memos) }}</div>
            <div class="kpi-value {{ \App\Support\Money::isNegative($due) ? '' : 'neg' }}">{{ $type->outstandingLabel() }} {{ $currency->format($due) }}</div>
        </td>
        @endif
    @endforeach
    </tr>
</table>

<h2>رصيد مرحّل</h2>
<table class="data">
    <thead><tr><th>البيان</th><th>الاتجاه</th><th>المبلغ</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($vendor->balanceMemos as $memo)
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

<h2>{{ $type->chargesHeading() }}</h2>
<table class="data">
    <thead><tr><th>التفاصيل</th><th>السعر</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($vendor->charges as $charge)
        <tr>
            <td>
                {{ $charge->title }}
                @if(filled($charge->notes))<div class="sub" style="white-space: pre-line;">{{ $charge->notes }}</div>@endif
            </td>
            <td>
                {{ $charge->currency->format($charge->amount) }}
                @if($charge->isFx())
                    <div class="sub">{{ $charge->fxCurrency?->format($charge->source_amount) }} × {{ $charge->formattedExchangeRate() }}</div>
                @endif
            </td>
            <td>{{ format_date($charge->charge_date) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا توجد سجلات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>خصم/أخرى</h2>
<table class="data">
    <thead><tr><th>التصنيف</th><th>السعر</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($vendor->goodsTakes as $take)
        <tr>
            <td>
                {{ $take->title }}
                @if(filled($take->notes))<div class="sub" style="white-space: pre-line;">{{ $take->notes }}</div>@endif
            </td>
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
    <thead><tr><th>الاسم</th><th>المبلغ</th><th>الدرج</th><th>الطريقة</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($vendor->cashPayments as $p)
        <tr>
            <td>
                {{ $p->name }}{{ $p->is_reversed ? ' (ملغاة)' : '' }}
                @if(filled($p->notes))<div class="sub" style="white-space: pre-line;">{{ $p->notes }}</div>@endif
            </td>
            <td class="amount">{{ $p->currency->format($p->amount) }}</td>
            <td>{{ $p->fund->name }}</td>
            <td>{{ $p->paymentMethod->name }}</td>
            <td>{{ format_date($p->occurred_on) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="empty">لا توجد دفعات.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
