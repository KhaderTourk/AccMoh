@extends('cp.layout')
@section('title', 'التقارير')
@section('content')
<div class="space-y-8">
    @php
        $filterCount = collect(['from', 'to', '_preset'])->filter(fn ($key) => filled(request($key)))->count();
    @endphp
    @component('cp.partials.filter-panel', ['count' => $filterCount])
        @slot('actions')
            <a href="{{ route('cp.reports.export-pdf', array_filter(['from' => $from, 'to' => $to])) }}" class="cp-btn cp-btn-ghost">
                <span class="material-symbols-outlined">picture_as_pdf</span>
                تصدير PDF
            </a>
        @endslot
        @include('cp.partials.date-range-fields')
        @slot('footer')
            @include('cp.partials.date-range-shortcuts')
        @endslot
    @endcomponent

    @if(tenantBusinessEnabled() && count($profitRows))
    <section>
        <h2 class="font-bold text-lg mb-3">أرباح الفترة</h2>
        <div class="grid md:grid-cols-2 gap-4">
            @foreach($profitRows as $row)
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5 space-y-2 text-sm">
                <h3 class="font-bold text-base">{{ $row['currency']->name }}</h3>
                <p>دفعات واردة من الزبائن: <strong class="text-emerald-600">{{ $row['currency']->format($row['payments']) }}</strong></p>
                <p>صادر العمل: <strong class="text-rose-600">{{ $row['currency']->format($row['work_expenses']) }}</strong></p>
                <p class="text-xs text-slate-500">منها الموظفون {{ $row['currency']->format($row['worker_expenses']) }} · الموردون {{ $row['currency']->format($row['supplier_expenses']) }}</p>
                <p>مستحقات الزبائن: <strong>{{ $row['currency']->format($row['client_outstanding']) }}</strong></p>
                <p>مستحقات الموظفين: <strong class="text-amber-700 dark:text-amber-300">{{ $row['currency']->format($row['worker_outstanding']) }}</strong></p>
                <p>مستحقات الموردين: <strong class="text-amber-700 dark:text-amber-300">{{ $row['currency']->format($row['supplier_outstanding']) }}</strong></p>
                <p class="pt-2 border-t">صافي الأرباح = دفعات الزبائن − صادر العمل:
                    <strong class="{{ \App\Support\Money::isNegative($row['net_profit']) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $row['currency']->format($row['net_profit']) }}</strong>
                </p>
                <p>إجمالي الأرباح = مستحقات الموظفين والموردين + دفعات الزبائن − صادر العمل:
                    <strong class="{{ \App\Support\Money::isNegative($row['gross_profit']) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $row['currency']->format($row['gross_profit']) }}</strong>
                </p>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <section>
        <h2 class="font-bold text-lg mb-3">تقرير الأرصدة</h2>
        <div class="overflow-x-auto rounded-2xl border bg-white dark:bg-slate-800">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">الدرج</th>@foreach($snapshot['currencies'] as $c)<th class="px-3 py-2">{{ $c->name }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($snapshot['funds'] as $fund)
                    <tr class="border-t"><td class="px-3 py-2 font-medium">{{ $fund->name }}</td>@foreach($snapshot['currencies'] as $c)<td class="px-3 py-2">{{ $c->format($snapshot['fundTotals'][$fund->id][$c->id] ?? 0) }}</td>@endforeach</tr>
                @endforeach
                <tr class="border-t font-bold bg-primary/5"><td class="px-3 py-2">الإجمالي</td>@foreach($snapshot['currencies'] as $c)<td class="px-3 py-2">{{ $c->format($snapshot['grand'][$c->id] ?? 0) }}</td>@endforeach</tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid md:grid-cols-{{ tenantBusinessEnabled() ? '2' : '1' }} lg:grid-cols-{{ tenantBusinessEnabled() ? '4' : '1' }} gap-4">
        @if(tenantBusinessEnabled())
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-800">
            <h3 class="font-bold mb-2">مستحقات الزبائن</h3>
            @foreach($snapshot['currencies'] as $c)<p>{{ $c->format($receivables[$c->id] ?? 0) }}</p>@endforeach
        </div>
        <div class="rounded-2xl border p-4 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/40">
            <h3 class="font-bold mb-2 text-amber-800 dark:text-amber-300">مستحقات الموظفين</h3>
            @foreach($snapshot['currencies'] as $c)<p class="font-extrabold">{{ $c->format($workerPayables[$c->id] ?? 0) }}</p>@endforeach
        </div>
        <div class="rounded-2xl border p-4 bg-sky-50 dark:bg-sky-900/20 border-sky-200 dark:border-sky-800/40">
            <h3 class="font-bold mb-2 text-sky-800 dark:text-sky-300">مستحقات الموردين</h3>
            @foreach($snapshot['currencies'] as $c)<p class="font-extrabold">{{ $c->format($supplierPayables[$c->id] ?? 0) }}</p>@endforeach
        </div>
        @endif
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-800">
            <h3 class="font-bold mb-2">صافي دفعات الأشخاص</h3>
            @foreach($snapshot['currencies'] as $c)
                @php $net = $personNet[$c->id] ?? '0'; @endphp
                <p class="{{ \App\Support\Money::isNegative($net) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $c->format($net) }}</p>
            @endforeach
        </div>
    </section>

    @if(tenantBusinessEnabled())
    <section>
        <h2 class="font-bold text-lg mb-3">تقرير الزبائن</h2>
        <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">الزبون</th><th class="px-3 py-2">العملة</th><th class="px-3 py-2">الخدمات</th><th class="px-3 py-2">المدفوع</th><th class="px-3 py-2">المتبقي</th></tr></thead>
                <tbody class="divide-y dark:divide-slate-700">
                @forelse($clientSummary as $row)
                    @foreach($row['rows'] as $r)
                    <tr>
                        <td class="px-3 py-2">{{ $row['client']->name }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->name }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->format($r['billed']) }}</td>
                        <td class="px-3 py-2 text-emerald-600">{{ $r['currency']->format($r['paid']) }}</td>
                        <td class="px-3 py-2 font-bold">
                            @if(\App\Support\Money::isNegative($r['due']))
                                عربون {{ $r['currency']->format(\App\Support\Money::abs($r['due'])) }}
                            @else
                                {{ $r['currency']->format($r['due']) }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-slate-500">لا بيانات.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @endif

    @if(tenantBusinessEnabled())
        @include('cp.finance.reports.vendor-table', [
            'summary' => $workerSummary,
            'heading' => 'تقرير الموظفين',
            'nameLabel' => 'الموظف',
            'billedLabel' => 'قيمة المستحقات',
            'empty' => 'لا بيانات للموظفين.',
            'routePrefix' => 'workers',
        ])
        @include('cp.finance.reports.vendor-table', [
            'summary' => $supplierSummary,
            'heading' => 'تقرير الموردين',
            'nameLabel' => 'المورد',
            'billedLabel' => 'قيمة ما تم تلقيه',
            'empty' => 'لا بيانات للموردين.',
            'routePrefix' => 'suppliers',
        ])
    @endif

    <section>
        <h2 class="font-bold text-lg mb-3">تقرير الأشخاص</h2>
        <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">الشخص</th><th class="px-3 py-2">العملة</th><th class="px-3 py-2">وارد</th><th class="px-3 py-2">صادر</th></tr></thead>
                <tbody class="divide-y dark:divide-slate-700">
                @forelse($personSummary as $row)
                    @foreach($row['rows'] as $r)
                    <tr>
                        <td class="px-3 py-2">{{ $row['member']->name }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->name }}</td>
                        <td class="px-3 py-2 text-emerald-600">{{ $r['currency']->format($r['in']) }}</td>
                        <td class="px-3 py-2 text-rose-600">{{ $r['currency']->format($r['out']) }}</td>
                    </tr>
                    @endforeach
                @empty
                    <tr><td colspan="4" class="p-6 text-center text-slate-500">لا بيانات.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid lg:grid-cols-2 gap-6">
        <div>
            <h2 class="font-bold text-lg mb-3">دفعات واردة</h2>
            <div class="rounded-2xl border bg-white dark:bg-slate-800 max-h-96 overflow-y-auto">
                @forelse($incoming as $p)
                    <div class="px-3 py-2 border-b text-sm flex justify-between gap-3 bg-emerald-50/70 dark:bg-emerald-900/20">
                        <span>
                            {{ $p->name }} — {{ $p->occurred_on->format('Y-m-d') }}
                            @include('cp.partials.note-line', ['notes' => $p->notes])
                        </span>
                        <strong class="text-emerald-600">{{ $p->currency->format($p->amount) }}</strong>
                    </div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا دفعات واردة في الفترة.</p>
                @endforelse
            </div>
        </div>
        <div>
            <h2 class="font-bold text-lg mb-3">دفعات صادرة</h2>
            <div class="rounded-2xl border bg-white dark:bg-slate-800 max-h-96 overflow-y-auto">
                @forelse($outgoing as $p)
                    <div class="px-3 py-2 border-b text-sm flex justify-between gap-3 bg-rose-50/70 dark:bg-rose-900/20">
                        <span>
                            {{ $p->name }} — {{ $p->occurred_on->format('Y-m-d') }}
                            @include('cp.partials.note-line', ['notes' => $p->notes])
                        </span>
                        <strong class="text-rose-600">{{ $p->currency->format($p->amount) }}</strong>
                    </div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا دفعات صادرة في الفترة.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
