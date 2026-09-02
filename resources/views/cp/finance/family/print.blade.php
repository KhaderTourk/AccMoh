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
            $owe = $member->iOweAmount($c->id);
            $owed = $member->theyOweAmount($c->id);
            $net = \App\Support\Money::sub($owed, $owe);
        @endphp
        <td>
            <div class="kpi-label">{{ $c->name }}</div>
            <div class="sub">دائن (عليّ): {{ $c->format($owe) }}</div>
            <div class="sub">مدين (لي): {{ $c->format($owed) }}</div>
            @if(\App\Support\Money::isPositive($net))
                <div class="kpi-value">صافٍ لي {{ $c->format($net) }}</div>
            @elseif(\App\Support\Money::isNegative($net))
                <div class="kpi-value neg">صافٍ عليّ {{ $c->format(\App\Support\Money::abs($net)) }}</div>
            @else
                <div class="kpi-value">متعادل</div>
            @endif
        </td>
    @endforeach
    </tr>
</table>

<h2>دائن ومدين</h2>
<table class="data">
    <thead><tr><th>النوع</th><th>الأصل</th><th>المتبقي</th><th>الطريقة</th><th>التاريخ</th><th>الحالة</th></tr></thead>
    <tbody>
    @forelse($member->loans as $loan)
        <tr>
            <td>
                {{ $loan->direction->label() }}
                @if(filled($loan->notes))
                    <div class="sub" style="white-space: pre-line;">{{ $loan->notes }}</div>
                @endif
            </td>
            <td>
                {{ $loan->currency->format($loan->amount) }}
                @if($loan->isFx())
                    <div class="sub">{{ $loan->fxCurrency?->format($loan->source_amount) }} × {{ $loan->formattedExchangeRate() }}</div>
                @endif
            </td>
            <td>{{ $loan->currency->format($loan->remainingAmount()) }}</td>
            <td>{{ $loan->paymentMethod->name }}</td>
            <td>{{ $loan->loan_date->format('Y-m-d') }}</td>
            <td>{{ $loan->is_reversed ? 'ملغاة' : $loan->status->label() }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">لا توجد حركات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>التسويات</h2>
<table class="data">
    <thead><tr><th>النوع</th><th>المبلغ</th><th>الطريقة</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($member->repayments as $repayment)
        <tr>
            <td>
                {{ $repayment->direction->label() }}{{ $repayment->is_reversed ? ' (ملغاة)' : '' }}
                @if(filled($repayment->notes))
                    <div class="sub" style="white-space: pre-line;">{{ $repayment->notes }}</div>
                @endif
            </td>
            <td class="amount">{{ $repayment->currency->format($repayment->amount) }}</td>
            <td>{{ $repayment->paymentMethod->name }}</td>
            <td>{{ $repayment->repayment_date->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">لا توجد تسويات.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
