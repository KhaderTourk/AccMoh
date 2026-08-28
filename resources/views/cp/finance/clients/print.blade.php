@extends('cp.print')
@section('content')
@php $title = $client->name; @endphp
<h1>{{ $client->name }}</h1>
<p class="muted">
    @if($client->contact_name){{ $client->contact_name }} · @endif
    {{ $client->phone }}
    — تاريخ التصدير: {{ $exportedAt ?? now()->format('Y-m-d H:i') }}
</p>

@foreach($currencies as $currency)
    @php
        $billed = $client->billedAmount($currency->id);
        $paid = $client->paidAmount($currency->id);
        $due = $client->outstandingAmount($currency->id);
    @endphp
    @if(!\App\Support\Money::isZero($billed) || !\App\Support\Money::isZero($paid))
    <div class="card">
        <strong>{{ $currency->name }}</strong><br>
        قيمة الخدمات: {{ $currency->format($billed) }}<br>
        المدفوع: {{ $currency->format($paid) }}<br>
        @if(\App\Support\Money::isNegative($due))
            عربون / رصيد مدفوع مقدماً: {{ $currency->format(\App\Support\Money::abs($due)) }}
        @else
            المتبقي: {{ $currency->format($due) }}
        @endif
    </div>
    @endif
@endforeach

<h2>الخدمات</h2>
<table>
    <thead><tr><th>الخدمة</th><th>السعر</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($client->services as $service)
        <tr>
            <td>{{ $service->title }}</td>
            <td>
                {{ $service->currency->format($service->amount) }}
                @if($service->isFx())
                    — {{ $service->fxCurrency?->format($service->source_amount) }} × {{ $service->formattedExchangeRate() }}
                @endif
            </td>
            <td>{{ $service->service_date->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="3">لا توجد خدمات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>الدفعات</h2>
<table>
    <thead><tr><th>المبلغ</th><th>الطريقة</th><th>المرسل</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($client->payments as $payment)
        <tr>
            <td>
                {{ $payment->currency->format($payment->amount) }}
                @if($payment->is_reversed) (ملغاة) @endif
                @if($payment->isFx())
                    — {{ $payment->fxCurrency?->format($payment->source_amount) }} × {{ $payment->formattedExchangeRate() }}
                @endif
            </td>
            <td>{{ $payment->paymentMethod->name }}</td>
            <td>{{ $payment->payer_name }}</td>
            <td>{{ $payment->payment_date->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="4">لا توجد دفعات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>السجل الزمني</h2>
<table>
    <thead><tr><th>التاريخ</th><th>الحركة</th><th>المبلغ</th></tr></thead>
    <tbody>
    @foreach($timeline as $item)
        <tr>
            <td>{{ $item['date']->format('Y-m-d') }}</td>
            <td>{{ $item['title'] }}</td>
            <td>{{ $item['currency']->format($item['amount']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
