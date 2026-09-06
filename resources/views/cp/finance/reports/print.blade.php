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
                    صادر العمل: {{ $row['currency']->format($row['work_expenses']) }}<br>
                    الموظفين {{ $row['currency']->format($row['worker_expenses']) }} · موردون {{ $row['currency']->format($row['supplier_expenses']) }}<br>
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
            <th>الدرج</th>
            @foreach($snapshot['currencies'] as $c)<th>{{ $c->name }}</th>@endforeach
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
            <div class="kpi-label">مستحقات الزبائن</div>
            @foreach($snapshot['currencies'] as $c)
                <div class="kpi-value">{{ $c->format($receivables[$c->id] ?? 0) }}</div>
            @endforeach
        </td>
        @endif
        <td>
            <div class="kpi-label">صافي دفعات الأشخاص</div>
            @foreach($snapshot['currencies'] as $c)
                @php $net = $personNet[$c->id] ?? '0'; @endphp
                <div class="kpi-value {{ \App\Support\Money::isNegative($net) ? 'neg' : '' }}">{{ $c->format($net) }}</div>
            @endforeach
        </td>
    </tr>
</table>

@if(tenantBusinessEnabled())
<h2>تقرير الزبائن</h2>
<table class="data">
    <thead><tr><th>الزبون</th><th>العملة</th><th>الخدمات</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
    <tbody>
    @forelse($clientSummary as $row)
        @foreach($row['rows'] as $r)
        <tr>
            <td>{{ $row['client']->name }}</td>
            <td>{{ $r['currency']->name }}</td>
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

<h2>تقرير الأشخاص</h2>
<table class="data">
    <thead><tr><th>الشخص</th><th>العملة</th><th>وارد</th><th>صادر</th></tr></thead>
    <tbody>
    @forelse($personSummary as $row)
        @foreach($row['rows'] as $r)
        <tr>
            <td>{{ $row['member']->name }}</td>
            <td>{{ $r['currency']->name }}</td>
            <td>{{ $r['currency']->format($r['in']) }}</td>
            <td>{{ $r['currency']->format($r['out']) }}</td>
        </tr>
        @endforeach
    @empty
        <tr><td colspan="4" class="empty">لا بيانات.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>دفعات واردة</h2>
<table class="data">
    <thead><tr><th>الاسم</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($incoming as $p)
        <tr>
            <td>
                {{ $p->name }}
                @if(filled($p->notes))
                    <div class="sub" style="white-space: pre-line;">{{ $p->notes }}</div>
                @endif
            </td>
            <td>{{ $p->occurred_on->format('Y-m-d') }}</td>
            <td class="amount">{{ $p->currency->format($p->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا دفعات واردة في الفترة.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>دفعات صادرة</h2>
<table class="data">
    <thead><tr><th>الاسم</th><th>التاريخ</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($outgoing as $p)
        <tr>
            <td>
                {{ $p->name }}
                @if(filled($p->notes))
                    <div class="sub" style="white-space: pre-line;">{{ $p->notes }}</div>
                @endif
            </td>
            <td>{{ $p->occurred_on->format('Y-m-d') }}</td>
            <td class="amount">{{ $p->currency->format($p->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="empty">لا دفعات صادرة في الفترة.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
