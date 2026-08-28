@extends('cp.print')
@section('content')
@php
    $hasBalances = false;
@endphp
@foreach($currencies as $currency)
    @php
        $billed = $client->billedAmount($currency->id);
        $paid = $client->paidAmount($currency->id);
        $due = $client->outstandingAmount($currency->id);
    @endphp
    @if(!\App\Support\Money::isZero($billed) || !\App\Support\Money::isZero($paid))
        @php $hasBalances = true; @endphp
    @endif
@endforeach

@if($hasBalances)
<h2>ملخص الحساب</h2>
<table class="kpis">
    <tr>
    @foreach($currencies as $currency)
        @php
            $billed = $client->billedAmount($currency->id);
            $paid = $client->paidAmount($currency->id);
            $due = $client->outstandingAmount($currency->id);
        @endphp
        @if(!\App\Support\Money::isZero($billed) || !\App\Support\Money::isZero($paid))
        <td>
            <div class="kpi-label">{{ $currency->name }}</div>
            <div class="sub">قيمة الخدمات: {{ $currency->format($billed) }}</div>
            <div class="sub">المدفوع: {{ $currency->format($paid) }}</div>
            @if(\App\Support\Money::isNegative($due))
                <div class="kpi-value">عربون {{ $currency->format(\App\Support\Money::abs($due)) }}</div>
            @else
                <div class="kpi-value {{ \App\Support\Money::isZero($due) ? '' : 'neg' }}">المتبقي {{ $currency->format($due) }}</div>
            @endif
        </td>
        @endif
    @endforeach
    </tr>
</table>
@endif

<h2>الخدمات</h2>
<table class="data">
    <thead><tr><th>الخدمة</th><th>السعر</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($client->services as $service)
        <tr>
            <td>{{ $service->title }}</td>
            <td>
                {{ $service->currency->format($service->amount) }}
                @if($service->isFx())
                    <div class="sub">{{ $service->fxCurrency?->format($service->source_amount) }} × {{ $service->formattedExchangeRate() }}</div>
                @endif
            </td>
            <td>{{ $service->service_date->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا توجد خدمات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>الدفعات</h2>
<table class="data">
    <thead><tr><th>المبلغ</th><th>الطريقة</th><th>المرسل</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($client->payments as $payment)
        <tr>
            <td>
                {{ $payment->currency->format($payment->amount) }}
                @if($payment->is_reversed) <span class="sub">(ملغاة)</span> @endif
                @if($payment->isFx())
                    <div class="sub">{{ $payment->fxCurrency?->format($payment->source_amount) }} × {{ $payment->formattedExchangeRate() }}</div>
                @endif
            </td>
            <td>{{ $payment->paymentMethod->name }}</td>
            <td>{{ $payment->payer_name }}</td>
            <td>{{ $payment->payment_date->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">لا توجد دفعات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>السجل الزمني</h2>
<table class="data">
    <thead><tr><th>التاريخ</th><th>الحركة</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($timeline as $item)
        <tr>
            <td>{{ $item['date']->format('Y-m-d') }}</td>
            <td>{{ $item['title'] }}</td>
            <td class="amount">{{ $item['currency']->format($item['amount']) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا حركات.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
