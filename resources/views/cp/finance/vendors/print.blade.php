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
            $due = $vendor->outstandingAmount($currency->id);
        @endphp
        @if(!\App\Support\Money::isZero($billed) || !\App\Support\Money::isZero($paid))
        <td>
            <div class="kpi-label">{{ $currency->name }}</div>
            <div class="sub">{{ $type->billedLabel() }}: {{ $currency->format($billed) }}</div>
            <div class="sub">المدفوع: {{ $currency->format($paid) }}</div>
            <div class="kpi-value {{ \App\Support\Money::isNegative($due) ? '' : 'neg' }}">{{ $type->outstandingLabel() }} {{ $currency->format($due) }}</div>
        </td>
        @endif
    @endforeach
    </tr>
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
            <td>{{ $charge->charge_date->format('Y-m-d') }}</td>
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
            <td>{{ $p->occurred_on->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="empty">لا توجد دفعات.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
