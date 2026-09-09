@extends('cp.print')
@section('content')
@if(!empty($summaries))
<h2>ملخص الحساب{{ !empty($hasOpening) || !empty($from) || !empty($to) ? ' — '.$periodLabel : '' }}</h2>
<table class="kpis">
    <tr>
    @foreach($summaries as $i => $row)
        @if($i && $i % 3 === 0)</tr><tr>@endif
        @php $currency = $row['currency']; @endphp
        <td>
            <div class="kpi-label">{{ $currency->name }}</div>
            @if(!empty($hasOpening))
                <div class="sub">رصيد سابق: {{ $currency->format($row['opening']) }}@if(!empty($row['opening_overridden'])) (معدّل للعرض)@endif</div>
            @endif
            <div class="sub">قيمة الخدمات: {{ $currency->format($row['billed']) }}</div>
            <div class="sub">المدفوع: {{ $currency->format($row['paid']) }}</div>
            @if(\App\Support\Money::isNegative($row['closing']))
                <div class="kpi-value">عربون {{ $currency->format(\App\Support\Money::abs($row['closing'])) }}</div>
            @else
                <div class="kpi-value {{ \App\Support\Money::isZero($row['closing']) ? '' : 'neg' }}">المتبقي {{ $currency->format($row['closing']) }}</div>
            @endif
        </td>
    @endforeach
    </tr>
</table>
@if(!empty($hasOpening))
<p class="muted">الرصيد السابق للعرض في هذا الكشف فقط، ولا يغيّر الأرصدة المسجّلة في النظام.</p>
@endif
@endif

@if(filled($client->notes))
<div class="card">
    <div class="kpi-label">ملاحظات</div>
    <div class="sub" style="white-space: pre-line;">{{ $client->notes }}</div>
</div>
@endif

<h2>الخدمات</h2>
@forelse($serviceGroups as $group)
    <h3 style="font-size:12px;margin:12px 0 6px;color:#047857;">{{ $group['name'] }}
        @if($group['totals']->isNotEmpty())
            — @foreach($group['totals'] as $total){{ $total['formatted'] }}@if(! $loop->last) · @endif @endforeach
        @endif
    </h3>
    <table class="data">
        <thead><tr><th>تفاصيل الخدمة</th><th>السعر</th><th>التاريخ</th></tr></thead>
        <tbody>
        @foreach($group['services'] as $service)
            <tr>
                <td>
                    {{ $service->title }}
                    @if(filled($service->notes))
                        <div class="sub" style="white-space: pre-line;">{{ $service->notes }}</div>
                    @endif
                </td>
                <td>
                    {{ $service->currency->format($service->amount) }}
                    @if($service->isFx())
                        <div class="sub">{{ $service->fxCurrency?->format($service->source_amount) }} × {{ $service->formattedExchangeRate() }}</div>
                    @endif
                </td>
                <td>{{ $service->service_date->format('Y-m-d') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@empty
<table class="data">
    <tbody><tr><td class="empty">لا توجد خدمات{{ !empty($from) || !empty($to) ? ' في هذه الفترة' : '' }}.</td></tr></tbody>
</table>
@endforelse

<h2>الدفعات</h2>
@forelse($paymentGroups as $group)
    <h3 style="font-size:12px;margin:12px 0 6px;color:#047857;">{{ $group['name'] }}
        — الإجمالي:
        @forelse($group['totals'] as $total)
            {{ $total['formatted'] }}@if(! $loop->last) · @endif
        @empty
            0
        @endforelse
    </h3>
    <table class="data">
        <thead><tr><th>المبلغ</th><th>الاسم</th><th>التاريخ</th></tr></thead>
        <tbody>
        @foreach($group['payments'] as $payment)
            <tr>
                <td>
                    {{ $payment->currency->format($payment->amount) }}
                    @if($payment->is_reversed) <span class="sub">(ملغاة)</span> @endif
                    @if($payment->isFx())
                        <div class="sub">{{ $payment->fxCurrency?->format($payment->source_amount) }} × {{ $payment->formattedExchangeRate() }}</div>
                    @endif
                </td>
                <td>
                    {{ $payment->name }}
                    @if(filled($payment->notes))
                        <div class="sub" style="white-space: pre-line;">{{ $payment->notes }}</div>
                    @endif
                </td>
                <td>{{ $payment->occurred_on->format('Y-m-d') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@empty
<table class="data">
    <tbody><tr><td class="empty">لا توجد دفعات{{ !empty($from) || !empty($to) ? ' في هذه الفترة' : '' }}.</td></tr></tbody>
</table>
@endforelse

<h2>السجل الزمني</h2>
<table class="data">
    <thead><tr><th>التاريخ</th><th>الحركة</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($timeline as $item)
        <tr>
            <td>{{ $item['date']->format('Y-m-d') }}</td>
            <td>
                {{ $item['title'] }}
                @if(!empty($item['notes']))
                    <div class="sub" style="white-space: pre-line;">{{ $item['notes'] }}</div>
                @endif
            </td>
            <td class="amount">{{ $item['currency']->format($item['amount']) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا حركات{{ !empty($from) || !empty($to) ? ' في هذه الفترة' : '' }}.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
