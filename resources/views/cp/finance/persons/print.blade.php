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
            $net = $member->netAmount($c->id);
        @endphp
        @if(!\App\Support\Money::isZero($in) || !\App\Support\Money::isZero($out))
        <td>
            <div class="kpi-label">{{ $c->name }}</div>
            <div class="sub">وارد: {{ $c->format($in) }}</div>
            <div class="sub">صادر: {{ $c->format($out) }}</div>
            <div class="kpi-value {{ \App\Support\Money::isNegative($net) ? 'neg' : '' }}">الصافي {{ $c->format($net) }}</div>
        </td>
        @endif
    @endforeach
    </tr>
</table>

<h2>الدفعات</h2>
<table class="data">
    <thead><tr><th>التاريخ</th><th>النوع</th><th>الدرج</th><th>الطريقة</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($member->cashPayments as $p)
        <tr>
            <td>{{ $p->occurred_on->format('Y-m-d') }}</td>
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
