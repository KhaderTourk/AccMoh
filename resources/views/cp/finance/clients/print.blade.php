@extends('cp.print')
@section('content')
@php
    // كشف حساب على نمط برامج المحاسبة: جدول واحد مرتب زمنياً لكل عملة مع مجاميع المدين والدائن.
    $typeLabels = ['service' => 'خدمة', 'payment' => 'دفعة', 'memo' => 'رصيد مرحّل', 'goods' => 'بضاعة مأخوذة'];
    $chronological = collect($timeline)
        ->sortBy(fn ($item) => $item['date']->format('Y-m-d').sprintf('%010d', $item['sort_at'] ?? 0).sprintf('%010d', $item['id'] ?? 0))
        ->values();
    $byCurrency = $chronological->groupBy(fn ($item) => (int) $item['currency']->id);
    $openings = collect($summaries ?? [])->mapWithKeys(fn ($row) => [(int) $row['currency']->id => $row['opening'] ?? '0.00']);
    $multiCurrency = $byCurrency->count() > 1;
@endphp

@if(filled($client->notes))
<div class="card">
    <div class="kpi-label">ملاحظات</div>
    <div class="sub" style="white-space: pre-line;">{{ $client->notes }}</div>
</div>
@endif

<h2>السجل الزمني</h2>

@forelse($byCurrency as $currencyId => $items)
    @php
        $currency = $items->first()['currency'];
        $opening = (string) ($openings[$currencyId] ?? '0.00');
        $summaryRow = collect($summaries ?? [])->first(fn ($row) => (int) $row['currency']->id === (int) $currencyId);
        $openingOverridden = ! empty($summaryRow['opening_overridden']);
        $debitTotal = '0.00';
        $creditTotal = '0.00';
    @endphp
    @if($multiCurrency)
        <div class="cur-title"><span class="cur-chip">{{ $currency->name }}</span></div>
    @endif
    <table class="stmt">
        <thead>
            <tr>
                <th style="width:22px;">م</th>
                <th style="width:62px;">التاريخ</th>
                <th style="width:70px;">نوع الحركة</th>
                <th>البيان</th>
                <th style="width:78px;">مدين</th>
                <th style="width:78px;">دائن</th>
                <th style="width:84px;">الرصيد</th>
            </tr>
        </thead>
        <tbody>
        @if(!empty($hasOpening) || ! \App\Support\Money::isZero($opening))
            <tr class="opening">
                <td class="num">—</td>
                <td>{{ !empty($from) ? format_date($from) : '—' }}</td>
                <td>—</td>
                <td class="desc">رصيد سابق{{ $openingOverridden ? ' (معدّل للعرض)' : '' }}</td>
                <td>—</td>
                <td>—</td>
                <td class="bal">{{ $currency->format($opening) }}</td>
            </tr>
        @endif
        @foreach($items as $i => $item)
            @php
                $delta = (string) ($item['delta'] ?? '0');
                $isCredit = \App\Support\Money::isNegative($delta)
                    || (\App\Support\Money::isZero($delta) && in_array($item['type'] ?? '', ['payment', 'goods'], true));
                $counts = ! \App\Support\Money::isZero($delta);
                if (\App\Support\Money::isPositive($delta)) {
                    $debitTotal = \App\Support\Money::add($debitTotal, $delta);
                } elseif (\App\Support\Money::isNegative($delta)) {
                    $creditTotal = \App\Support\Money::add($creditTotal, \App\Support\Money::abs($delta));
                }
                $formattedAmount = $item['currency']->format($item['amount']);
                $type = $item['type'] ?? '';
            @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ format_date($item['date']) }}</td>
                <td><span class="type-tag t-{{ $type }}">{{ $typeLabels[$type] ?? '—' }}</span></td>
                <td class="desc">
                    {{ $item['title'] }}
                    @if(!$counts)
                        <span class="sub">(لا تُحتسب في الرصيد)</span>
                    @endif
                    @if(!empty($item['notes']))
                        <div class="sub" style="white-space: pre-line;">{{ $item['notes'] }}</div>
                    @endif
                </td>
                <td class="{{ $isCredit ? '' : 'debit' }}">{{ $isCredit ? '' : $formattedAmount }}</td>
                <td class="{{ $isCredit ? 'credit' : '' }}">{{ $isCredit ? $formattedAmount : '' }}</td>
                <td class="bal">{{ $item['currency']->format($item['balance']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @php
        $net = \App\Support\Money::add($debitTotal, \App\Support\Money::neg($creditTotal));
        $final = \App\Support\Money::add($opening, $net);
    @endphp
    <table class="stmt-foot">
        <tr>
            <td class="label">المجموع المدين</td>
            <td class="val val-debit">{{ $currency->format($debitTotal) }}</td>
            <td class="label">المجموع الدائن</td>
            <td class="val val-credit">{{ $currency->format($creditTotal) }}</td>
        </tr>
        @if(! \App\Support\Money::isZero($opening))
        <tr>
            <td class="label">الرصيد السابق</td>
            <td class="val">{{ $currency->format($opening) }}</td>
            <td class="label">صافي الفترة</td>
            <td class="val">{{ $currency->format($net) }}</td>
        </tr>
        @endif
        <tr class="grand">
            <td class="label">الإجمالي</td>
            <td class="val" colspan="3">
                @if(\App\Support\Money::isNegative($final))
                    {{ $currency->format(\App\Support\Money::abs($final)) }} — عربون (له)
                @elseif(\App\Support\Money::isZero($final))
                    {{ $currency->format($final) }} — الحساب مسدّد
                @else
                    {{ $currency->format($final) }} — مستحق (عليه)
                @endif
            </td>
        </tr>
    </table>
@empty
    <table class="data">
        <tbody><tr><td class="empty">لا حركات{{ !empty($from) || !empty($to) ? ' في هذه الفترة' : '' }}.</td></tr></tbody>
    </table>
@endforelse

@if(!empty($hasOpening))
<p class="muted">الرصيد السابق للعرض في هذا الكشف فقط، ولا يغيّر الأرصدة المسجّلة في النظام.</p>
@endif
@endsection
