@extends('cp.print')
@section('content')
@php $title = $member->name; @endphp
<h1>{{ $member->name }}</h1>
<p class="muted">{{ $member->relationship }} {{ $member->phone }} — تاريخ التصدير: {{ $exportedAt ?? now()->format('Y-m-d H:i') }}</p>

@foreach($currencies as $c)
<div class="card">
    <strong>{{ $c->name }}</strong><br>
    دائن (عليّ): {{ $c->format($member->iOweAmount($c->id)) }}<br>
    مدين (لي): {{ $c->format($member->theyOweAmount($c->id)) }}
    @php
        $owe = $member->iOweAmount($c->id);
        $owed = $member->theyOweAmount($c->id);
        $net = \App\Support\Money::sub($owed, $owe);
    @endphp
    <br>
    @if(\App\Support\Money::isPositive($net)) صافٍ لي {{ $c->format($net) }}
    @elseif(\App\Support\Money::isNegative($net)) صافٍ عليّ {{ $c->format(\App\Support\Money::abs($net)) }}
    @else متعادل
    @endif
</div>
@endforeach

<h2>دائن ومدين</h2>
<table>
    <thead><tr><th>النوع</th><th>الأصل</th><th>المتبقي</th><th>الطريقة</th><th>التاريخ</th><th>الحالة</th></tr></thead>
    <tbody>
    @forelse($member->loans as $loan)
        <tr>
            <td>{{ $loan->direction->label() }}</td>
            <td>
                {{ $loan->currency->format($loan->amount) }}
                @if($loan->isFx())
                    — {{ $loan->fxCurrency?->format($loan->source_amount) }} × {{ $loan->formattedExchangeRate() }}
                @endif
            </td>
            <td>{{ $loan->currency->format($loan->remainingAmount()) }}</td>
            <td>{{ $loan->paymentMethod->name }}</td>
            <td>{{ $loan->loan_date->format('Y-m-d') }}</td>
            <td>{{ $loan->is_reversed ? 'ملغاة' : $loan->status->label() }}</td>
        </tr>
    @empty
        <tr><td colspan="6">لا توجد حركات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>التسويات</h2>
<table>
    <thead><tr><th>النوع</th><th>المبلغ</th><th>الطريقة</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($member->repayments as $repayment)
        <tr>
            <td>{{ $repayment->direction->label() }}{{ $repayment->is_reversed ? ' (ملغاة)' : '' }}</td>
            <td>{{ $repayment->currency->format($repayment->amount) }}</td>
            <td>{{ $repayment->paymentMethod->name }}</td>
            <td>{{ $repayment->repayment_date->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="4">لا توجد تسويات.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
