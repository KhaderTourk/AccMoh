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
                    دفعات الزبائن: {{ $row['currency']->format($row['payments']) }}<br>
                    صادر العمل: {{ $row['currency']->format($row['work_expenses']) }}<br>
                    الموظفون {{ $row['currency']->format($row['worker_expenses']) }} · موردون {{ $row['currency']->format($row['supplier_expenses']) }}<br>
                    إجمالي سعر الخدمات المقدمة للزبائن: {{ $row['currency']->format($row['client_billed']) }}<br>
                    مستحق على الزبائن: {{ $row['currency']->format($row['client_outstanding']) }}<br>
                    مستحقات الموظفين: {{ $row['currency']->format($row['worker_outstanding']) }}<br>
                    مستحقات الموردين: {{ $row['currency']->format($row['supplier_outstanding']) }}<br>
                    إجمالي الأرباح = إجمالي سعر الخدمات المقدمة للزبائن − صادر العمل − مستحقات الموظفين والموردين: {{ $row['currency']->format($row['gross_profit']) }}
                </div>
            </td>
        @endforeach
    </tr>
</table>
@endif

        <h2>الأرصدة الحالية</h2>
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
            <div class="kpi-label">مستحق على الزبائن</div>
            @foreach($snapshot['currencies'] as $c)
                <div class="kpi-value">{{ $c->format($receivables[$c->id] ?? 0) }}</div>
            @endforeach
        </td>
        <td>
            <div class="kpi-label">مستحقات الموظفين</div>
            @foreach($snapshot['currencies'] as $c)
                <div class="kpi-value">{{ $c->format($workerPayables[$c->id] ?? 0) }}</div>
            @endforeach
        </td>
        <td>
            <div class="kpi-label">مستحقات الموردين</div>
            @foreach($snapshot['currencies'] as $c)
                <div class="kpi-value">{{ $c->format($supplierPayables[$c->id] ?? 0) }}</div>
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

@if(tenantBusinessEnabled() && $clientSummary->isNotEmpty())
<h2>تقرير الزبائن</h2>
<table class="data">
    <thead><tr>
        <th>الزبون</th>
        <th>العملة</th>
        @if(!empty($hasOpening))<th>رصيد سابق</th>@endif
        <th>الخدمات</th>
        <th>المدفوع</th>
        <th>المتبقي</th>
    </tr></thead>
    <tbody>
    @foreach($clientSummary as $row)
        @foreach($row['rows'] as $r)
        <tr>
            <td>{{ $row['client']->personName() }}</td>
            <td>{{ $r['currency']->name }}</td>
            @if(!empty($hasOpening))
                <td>
                    @if(\App\Support\Money::isNegative($r['opening']))
                        عربون {{ $r['currency']->format(\App\Support\Money::abs($r['opening'])) }}
                    @else
                        {{ $r['currency']->format($r['opening']) }}
                    @endif
                </td>
            @endif
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
    @endforeach
    </tbody>
</table>
@endif

@if(tenantBusinessEnabled() && $workerSummary->isNotEmpty())
<h2>تقرير الموظفين</h2>
<table class="data">
    <thead><tr><th>الموظف</th><th>العملة</th><th>مستحق له</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
    <tbody>
    @foreach($workerSummary as $row)
        @foreach($row['rows'] as $r)
        <tr>
            <td>{{ $row['vendor']->name }}</td>
            <td>{{ $r['currency']->name }}</td>
            <td>{{ $r['currency']->format($r['billed']) }}</td>
            <td>{{ $r['currency']->format($r['paid']) }}</td>
            <td>
                @if(\App\Support\Money::isNegative($r['due']))
                    مقدماً {{ $r['currency']->format(\App\Support\Money::abs($r['due'])) }}
                @else
                    {{ $r['currency']->format($r['due']) }}
                @endif
            </td>
        </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
@endif

@if(tenantBusinessEnabled() && $supplierSummary->isNotEmpty())
<h2>تقرير الموردين</h2>
<table class="data">
    <thead><tr><th>المورد</th><th>العملة</th><th>مستحق له</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
    <tbody>
    @foreach($supplierSummary as $row)
        @foreach($row['rows'] as $r)
        <tr>
            <td>{{ $row['vendor']->name }}</td>
            <td>{{ $r['currency']->name }}</td>
            <td>{{ $r['currency']->format($r['billed']) }}</td>
            <td>{{ $r['currency']->format($r['paid']) }}</td>
            <td>
                @if(\App\Support\Money::isNegative($r['due']))
                    مقدماً {{ $r['currency']->format(\App\Support\Money::abs($r['due'])) }}
                @else
                    {{ $r['currency']->format($r['due']) }}
                @endif
            </td>
        </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
@endif

@if($personSummary->isNotEmpty())
<h2>تقرير الأشخاص</h2>
<table class="data">
    <thead><tr><th>الشخص</th><th>العملة</th><th>وارد</th><th>صادر</th></tr></thead>
    <tbody>
    @foreach($personSummary as $row)
        @foreach($row['rows'] as $r)
        <tr>
            <td>{{ $row['member']->name }}</td>
            <td>{{ $r['currency']->name }}</td>
            <td>{{ $r['currency']->format($r['in']) }}</td>
            <td>{{ $r['currency']->format($r['out']) }}</td>
        </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
@endif

<h2>دفعات واردة{{ isset($incomingCount) ? ' ('.$incomingCount.')' : '' }}</h2>
@if(!empty($incomingTotals) && $incomingTotals->isNotEmpty())
<p class="muted">الإجمالي: @foreach($incomingTotals as $total){{ $total['formatted'] }}@if(! $loop->last) · @endif @endforeach</p>
@endif
<table class="data">
    <thead><tr><th>الاسم</th><th>التاريخ</th><th>الطريقة</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($incoming as $p)
        <tr>
            <td>
                {{ $p->name }}
                @if(filled($p->notes))
                    <div class="sub" style="white-space: pre-line;">{{ $p->notes }}</div>
                @endif
            </td>
            <td>{{ format_date($p->occurred_on) }}</td>
            <td>{{ $p->paymentMethod?->name ?: '—' }}</td>
            <td class="amount">{{ $p->currency->format($p->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">لا دفعات واردة مطابقة للتصفية.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>دفعات صادرة{{ isset($outgoingCount) ? ' ('.$outgoingCount.')' : '' }}</h2>
@if(!empty($outgoingTotals) && $outgoingTotals->isNotEmpty())
<p class="muted">الإجمالي: @foreach($outgoingTotals as $total){{ $total['formatted'] }}@if(! $loop->last) · @endif @endforeach</p>
@endif
<table class="data">
    <thead><tr><th>الاسم</th><th>التاريخ</th><th>الطريقة</th><th>المبلغ</th></tr></thead>
    <tbody>
    @forelse($outgoing as $p)
        <tr>
            <td>
                {{ $p->name }}
                @if(filled($p->notes))
                    <div class="sub" style="white-space: pre-line;">{{ $p->notes }}</div>
                @endif
            </td>
            <td>{{ format_date($p->occurred_on) }}</td>
            <td>{{ $p->paymentMethod?->name ?: '—' }}</td>
            <td class="amount">{{ $p->currency->format($p->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">لا دفعات صادرة مطابقة للتصفية.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
