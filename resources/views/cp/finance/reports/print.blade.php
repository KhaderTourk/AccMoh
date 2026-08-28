@extends('cp.print')
@section('content')
@php $title = 'التقارير'; @endphp
<h1>التقارير</h1>
<p class="muted">
    تاريخ التصدير: {{ $exportedAt ?? now()->format('Y-m-d H:i') }}
    @if($from || $to)
        — الفترة: {{ $from ?: 'البداية' }} إلى {{ $to ?: 'اليوم' }}
    @else
        — طوال المدة
    @endif
</p>

@if(tenantBusinessEnabled() && count($profitRows))
<h2>أرباح الفترة</h2>
@foreach($profitRows as $row)
<div class="card">
    <strong>{{ $row['currency']->name }}</strong><br>
    دفعات العملاء: {{ $row['currency']->format($row['payments']) }}<br>
    مصروفات العمل: {{ $row['currency']->format($row['work_expenses']) }}
    (عمال {{ $row['currency']->format($row['worker_expenses']) }} · موردون {{ $row['currency']->format($row['supplier_expenses']) }})<br>
    المستحقات المتبقية: {{ $row['currency']->format($row['outstanding']) }}<br>
    صافي الأرباح: {{ $row['currency']->format($row['net_profit']) }}<br>
    إجمالي الأرباح: {{ $row['currency']->format($row['gross_profit']) }}
</div>
@endforeach
@endif

<h2>تقرير الأرصدة</h2>
<table>
    <thead>
        <tr>
            <th>الصندوق</th>
            @foreach($snapshot['currencies'] as $c)<th>{{ $c->code }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($snapshot['funds'] as $fund)
        <tr>
            <td>{{ $fund->name }}</td>
            @foreach($snapshot['currencies'] as $c)
                <td>{{ $c->format($snapshot['fundTotals'][$fund->id][$c->id] ?? 0) }}</td>
            @endforeach
        </tr>
        @endforeach
        <tr>
            <td><strong>الإجمالي</strong></td>
            @foreach($snapshot['currencies'] as $c)
                <td><strong>{{ $c->format($snapshot['grand'][$c->id] ?? 0) }}</strong></td>
            @endforeach
        </tr>
    </tbody>
</table>

@if(tenantBusinessEnabled())
<div class="card">
    <strong>مستحقات العملاء</strong><br>
    @foreach($snapshot['currencies'] as $c){{ $c->format($receivables[$c->id] ?? 0) }}<br>@endforeach
</div>
@endif
<div class="card">
    <strong>دائن (عليّ)</strong><br>
    @foreach($snapshot['currencies'] as $c){{ $c->format($iOwe[$c->id] ?? 0) }}<br>@endforeach
</div>
<div class="card">
    <strong>مدين (لي)</strong><br>
    @foreach($snapshot['currencies'] as $c){{ $c->format($theyOwe[$c->id] ?? 0) }}<br>@endforeach
</div>

@if(tenantBusinessEnabled())
<h2>تقرير العملاء</h2>
<table>
    <thead><tr><th>العميل</th><th>العملة</th><th>الخدمات</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
    <tbody>
    @forelse($clientSummary as $row)
        @foreach($row['rows'] as $r)
        <tr>
            <td>{{ $row['client']->name }}</td>
            <td>{{ $r['currency']->code }}</td>
            <td>{{ $r['currency']->format($r['billed']) }}</td>
            <td>{{ $r['currency']->format($r['paid']) }}</td>
            <td>
                @if(\App\Support\Money::isNegative($r['due']))
                    عربون {{ $r['currency']->format(\App\Support\Money::abs($r['due'])) }}
                @else
                    {{ $r['currency']->format($r['due']) }}
                @endif
            </td>
        </tr>
        @endforeach
    @empty
        <tr><td colspan="5">لا بيانات.</td></tr>
    @endforelse
    </tbody>
</table>
@endif

<h2>تقرير دائن ومدين — المفتوح</h2>
<table>
    <thead><tr><th>الشخص</th><th>النوع</th><th>المتبقي</th><th>التاريخ</th></tr></thead>
    <tbody>
    @forelse($openLoans as $loan)
        <tr>
            <td>{{ $loan->familyMember->name }}</td>
            <td>{{ $loan->direction->label() }}</td>
            <td>{{ $loan->currency->format($loan->remainingAmount()) }}</td>
            <td>{{ $loan->loan_date->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="4">لا حركات مفتوحة.</td></tr>
    @endforelse
    </tbody>
</table>

@if(tenantBusinessEnabled())
<h2>إيرادات الفترة</h2>
<table>
    <thead><tr><th>العميل</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($revenue as $p)
        <tr>
            <td>{{ $p->client->name }}</td>
            <td>{{ $p->payment_date->format('Y-m-d') }}</td>
            <td>{{ $p->currency->format($p->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="3">لا إيرادات في الفترة.</td></tr>
    @endforelse
    </tbody>
</table>
@endif

<h2>مصروفات الفترة</h2>
<table>
    <thead><tr><th>الجهة</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($expenses as $e)
        <tr>
            <td>{{ $e->description }}</td>
            <td>{{ $e->expense_date->format('Y-m-d') }}</td>
            <td>{{ $e->currency->format($e->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="3">لا مصروفات في الفترة.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
