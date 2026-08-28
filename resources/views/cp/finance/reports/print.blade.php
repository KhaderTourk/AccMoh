@extends('cp.print')
@section('content')
@if(tenantBusinessEnabled() && count($profitRows))
<h2>أرباح الفترة</h2>
<table class="kpis">
    <tr>
        @foreach($profitRows as $i => $row)
            @if($i && $i % 3 === 0)</tr><tr>@endif
            <td>
                <div class="kpi-label">{{ $row['currency']->name }} — صافي الأرباح</div>
                <div class="kpi-value {{ \App\Support\Money::isNegative($row['net_profit']) ? 'neg' : '' }}">{{ $row['currency']->format($row['net_profit']) }}</div>
                <div class="sub" style="margin-top:6px;">
                    دفعات: {{ $row['currency']->format($row['payments']) }}<br>
                    مصروفات العمل: {{ $row['currency']->format($row['work_expenses']) }}<br>
                    عمال {{ $row['currency']->format($row['worker_expenses']) }} · موردون {{ $row['currency']->format($row['supplier_expenses']) }}<br>
                    المستحقات: {{ $row['currency']->format($row['outstanding']) }}<br>
                    إجمالي الأرباح: {{ $row['currency']->format($row['gross_profit']) }}
                </div>
            </td>
        @endforeach
    </tr>
</table>
@endif

<h2>تقرير الأرصدة</h2>
<table class="data">
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
        <tr class="total">
            <td>الإجمالي</td>
            @foreach($snapshot['currencies'] as $c)
                <td>{{ $c->format($snapshot['grand'][$c->id] ?? 0) }}</td>
            @endforeach
        </tr>
    </tbody>
</table>

<table class="kpis">
    <tr>
        @if(tenantBusinessEnabled())
        <td>
            <div class="kpi-label">مستحقات العملاء</div>
            @foreach($snapshot['currencies'] as $c)
                <div class="kpi-value">{{ $c->format($receivables[$c->id] ?? 0) }}</div>
            @endforeach
        </td>
        @endif
        <td>
            <div class="kpi-label">دائن (عليّ)</div>
            @foreach($snapshot['currencies'] as $c)
                <div class="kpi-value neg">{{ $c->format($iOwe[$c->id] ?? 0) }}</div>
            @endforeach
        </td>
        <td>
            <div class="kpi-label">مدين (لي)</div>
            @foreach($snapshot['currencies'] as $c)
                <div class="kpi-value">{{ $c->format($theyOwe[$c->id] ?? 0) }}</div>
            @endforeach
        </td>
    </tr>
</table>

@if(tenantBusinessEnabled())
<h2>تقرير العملاء</h2>
<table class="data">
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
        <tr><td colspan="5" class="empty">لا بيانات.</td></tr>
    @endforelse
    </tbody>
</table>
@endif

<h2>تقرير دائن ومدين — المفتوح</h2>
<table class="data">
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
        <tr><td colspan="4" class="empty">لا حركات مفتوحة.</td></tr>
    @endforelse
    </tbody>
</table>

@if(tenantBusinessEnabled())
<h2>إيرادات الفترة</h2>
<table class="data">
    <thead><tr><th>العميل</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($revenue as $p)
        <tr>
            <td>{{ $p->client->name }}</td>
            <td>{{ $p->payment_date->format('Y-m-d') }}</td>
            <td class="amount">{{ $p->currency->format($p->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا إيرادات في الفترة.</td></tr>
    @endforelse
    </tbody>
</table>
@endif

<h2>مصروفات الفترة</h2>
<table class="data">
    <thead><tr><th>الجهة</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($expenses as $e)
        <tr>
            <td>{{ $e->description }}</td>
            <td>{{ $e->expense_date->format('Y-m-d') }}</td>
            <td class="amount">{{ $e->currency->format($e->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا مصروفات في الفترة.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
