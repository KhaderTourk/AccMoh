@extends('cp.layout')
@section('title', 'التقارير')
@section('content')
<div class="space-y-8">
    @php
        $filterCount = collect(['from', 'to', '_preset', 'currency_id', 'fund_id', 'payment_method_id', 'party_type', 'client_id', 'person_id', 'q'])
            ->filter(fn ($key) => filled(request($key)))->count();
        $filterQuery = $filterQuery ?? \App\Support\DateRange::queryParams($from, $to);
    @endphp
    @component('cp.partials.filter-panel', ['count' => $filterCount, 'open' => true])
        @slot('actions')
            <a href="{{ route('cp.reports.export-pdf', $filterQuery) }}" class="cp-btn cp-btn-ghost">
                <span class="material-symbols-outlined">picture_as_pdf</span>
                تصدير PDF
            </a>
        @endslot
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">بحث في الدفعات</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="الاسم أو الملاحظات" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">العملة</label>
            <select name="currency_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($currencies as $c)
                    <option value="{{ $c->id }}" @selected(request('currency_id')==$c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">الدرج</label>
            <select name="fund_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($funds as $f)
                    <option value="{{ $f->id }}" @selected(request('fund_id')==$f->id)>{{ $f->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">طريقة الدفع</label>
            <select name="payment_method_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($paymentMethods as $m)
                    <option value="{{ $m->id }}" @selected(request('payment_method_id')==$m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">نوع الطرف</label>
            <select name="party_type" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @if(tenantBusinessEnabled())
                    <option value="client" @selected(request('party_type')==='client')>زبائن</option>
                @endif
                <option value="person" @selected(request('party_type')==='person')>أشخاص</option>
                @if(tenantBusinessEnabled())
                    <option value="worker" @selected(request('party_type')==='worker')>موظفون</option>
                    <option value="supplier" @selected(request('party_type')==='supplier')>موردون</option>
                @endif
            </select>
        </div>
        @if(tenantBusinessEnabled())
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">زبون محدد</label>
            <select name="client_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->personName() }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">شخص محدد</label>
            <select name="person_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($persons as $p)
                    <option value="{{ $p->id }}" @selected(request('person_id')==$p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        @include('cp.partials.date-range-fields')
        @slot('footer')
            @include('cp.partials.date-range-shortcuts')
        @endslot
    @endcomponent

    <div class="rounded-xl border bg-white dark:bg-slate-800 px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-slate-500">نتائج التصفية:</span>
            <strong>{{ $periodLabel }}</strong>
            <span class="text-slate-500"> · وارد {{ $incomingCount }} · صادر {{ $outgoingCount }}</span>
        </div>
        <a href="{{ route('cp.reports.export-pdf', $filterQuery) }}" class="text-primary font-medium inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-base">picture_as_pdf</span>
            تصدير النتائج الحالية
        </a>
    </div>

    @if(tenantBusinessEnabled() && count($profitRows))
    <section>
        <h2 class="font-bold text-lg mb-3">أرباح الفترة</h2>
        <div class="grid md:grid-cols-2 gap-4">
            @foreach($profitRows as $row)
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5 space-y-2 text-sm">
                <h3 class="font-bold text-base">{{ $row['currency']->name }}</h3>
                <p>إجمالي سعر الخدمات المقدمة للزبائن: <strong>{{ $row['currency']->format($row['client_billed']) }}</strong></p>
                <p>دفعات واردة من الزبائن: <strong class="text-emerald-600">{{ $row['currency']->format($row['payments']) }}</strong></p>
                <p>صادر العمل: <strong class="text-rose-600">{{ $row['currency']->format($row['work_expenses']) }}</strong></p>
                <p class="text-xs text-slate-500">منها الموظفون {{ $row['currency']->format($row['worker_expenses']) }} · الموردون {{ $row['currency']->format($row['supplier_expenses']) }}</p>
                <p>مستحق على الزبائن: <strong>{{ $row['currency']->format($row['client_outstanding']) }}</strong></p>
                <p>مستحقات الموظفين: <strong class="text-amber-700 dark:text-amber-300">{{ $row['currency']->format($row['worker_outstanding']) }}</strong></p>
                <p>مستحقات الموردين: <strong class="text-amber-700 dark:text-amber-300">{{ $row['currency']->format($row['supplier_outstanding']) }}</strong></p>
                <p class="pt-2 border-t">صافي الأرباح = دفعات الزبائن − صادر العمل:
                    <strong class="{{ \App\Support\Money::isNegative($row['net_profit']) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $row['currency']->format($row['net_profit']) }}</strong>
                </p>
                <p>إجمالي الأرباح = إجمالي سعر الخدمات المقدمة للزبائن − صادر العمل − مستحقات الموظفين والموردين:
                    <strong class="{{ \App\Support\Money::isNegative($row['gross_profit']) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $row['currency']->format($row['gross_profit']) }}</strong>
                </p>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <section>
        <h2 class="font-bold text-lg mb-1">الأرصدة الحالية</h2>
        <p class="text-xs text-slate-500 mb-3">أرصدة الأدراج الحالية في النظام، ولا تتأثر بتصفية الفترة.</p>
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
            <h3 class="font-bold mb-2">مستحق على الزبائن</h3>
            <p class="text-xs text-slate-500 mb-1">الرصيد الحالي</p>
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

    @if(tenantBusinessEnabled() && request('party_type') !== 'person' && request('party_type') !== 'worker' && request('party_type') !== 'supplier')
    <section>
        <h2 class="font-bold text-lg mb-3">تقرير الزبائن{{ $from || $to ? ' — '.$periodLabel : '' }}</h2>
        <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                    <th class="px-3 py-2">الزبون</th>
                    <th class="px-3 py-2">العملة</th>
                    @if(!empty($hasOpening))<th class="px-3 py-2">رصيد سابق</th>@endif
                    <th class="px-3 py-2">الخدمات</th>
                    <th class="px-3 py-2">المدفوع</th>
                    <th class="px-3 py-2">المتبقي</th>
                </tr></thead>
                <tbody class="divide-y dark:divide-slate-700">
                @forelse($clientSummary as $row)
                    @foreach($row['rows'] as $r)
                    <tr>
                        <td class="px-3 py-2">
                            <a href="{{ route('cp.clients.show', array_merge(['client' => $row['client']], \App\Support\DateRange::queryParams($from, $to))) }}" class="text-primary font-medium">{{ $row['client']->personName() }}</a>
                        </td>
                        <td class="px-3 py-2">{{ $r['currency']->name }}</td>
                        @if(!empty($hasOpening))
                            <td class="px-3 py-2">
                                @if(\App\Support\Money::isNegative($r['opening']))
                                    عربون {{ $r['currency']->format(\App\Support\Money::abs($r['opening'])) }}
                                @else
                                    {{ $r['currency']->format($r['opening']) }}
                                @endif
                            </td>
                        @endif
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
                    <tr><td colspan="{{ !empty($hasOpening) ? 6 : 5 }}" class="p-6 text-center text-slate-500">لا بيانات مطابقة للتصفية.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @endif

    @if(tenantBusinessEnabled() && request('party_type') !== 'person' && request('party_type') !== 'client' && request('party_type') !== 'supplier')
        @include('cp.finance.reports.vendor-table', [
            'summary' => $workerSummary,
            'heading' => 'تقرير الموظفين'.(($from || $to) ? ' — '.$periodLabel : ''),
            'nameLabel' => 'الموظف',
            'billedLabel' => 'مستحق له',
            'empty' => 'لا بيانات للموظفين.',
            'routePrefix' => 'workers',
        ])
    @endif
    @if(tenantBusinessEnabled() && request('party_type') !== 'person' && request('party_type') !== 'client' && request('party_type') !== 'worker')
        @include('cp.finance.reports.vendor-table', [
            'summary' => $supplierSummary,
            'heading' => 'تقرير الموردين'.(($from || $to) ? ' — '.$periodLabel : ''),
            'nameLabel' => 'المورد',
            'billedLabel' => 'مستحق له',
            'empty' => 'لا بيانات للموردين.',
            'routePrefix' => 'suppliers',
        ])
    @endif

    @if(request('party_type') !== 'client' && request('party_type') !== 'worker' && request('party_type') !== 'supplier')
    <section>
        <h2 class="font-bold text-lg mb-3">تقرير الأشخاص{{ $from || $to ? ' — '.$periodLabel : '' }}</h2>
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
                    <tr><td colspan="4" class="p-6 text-center text-slate-500">لا بيانات مطابقة للتصفية.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @endif

    <section class="grid lg:grid-cols-2 gap-6">
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 class="font-bold text-lg">دفعات واردة ({{ $incomingCount }})</h2>
                @if($incomingTotals->isNotEmpty())
                    <div class="text-sm font-bold text-emerald-700 dark:text-emerald-300">
                        @foreach($incomingTotals as $total){{ $total['formatted'] }}@if(! $loop->last) · @endif @endforeach
                    </div>
                @endif
            </div>
            <div class="rounded-2xl border bg-white dark:bg-slate-800 max-h-96 overflow-y-auto">
                @forelse($incoming as $p)
                    <div class="px-3 py-2 border-b text-sm flex justify-between gap-3 bg-emerald-50/70 dark:bg-emerald-900/20">
                        <span>
                            <a href="{{ route('cp.payments.show', $p) }}" class="font-medium">{{ $p->name }}</a>
                            <span class="text-slate-500"> — {{ format_date($p->occurred_on) }}@if($p->paymentMethod) · {{ $p->paymentMethod->name }}@endif</span>
                            @include('cp.partials.note-line', ['notes' => $p->notes])
                        </span>
                        <strong class="text-emerald-600 whitespace-nowrap">{{ $p->currency->format($p->amount) }}</strong>
                    </div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا دفعات واردة مطابقة للتصفية.</p>
                @endforelse
                @if($incomingCount > $incoming->count())
                    <p class="p-3 text-xs text-slate-500 text-center">يُعرض {{ $incoming->count() }} من {{ $incomingCount }}. التصدير يشمل الكل.</p>
                @endif
            </div>
        </div>
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 class="font-bold text-lg">دفعات صادرة ({{ $outgoingCount }})</h2>
                @if($outgoingTotals->isNotEmpty())
                    <div class="text-sm font-bold text-rose-700 dark:text-rose-300">
                        @foreach($outgoingTotals as $total){{ $total['formatted'] }}@if(! $loop->last) · @endif @endforeach
                    </div>
                @endif
            </div>
            <div class="rounded-2xl border bg-white dark:bg-slate-800 max-h-96 overflow-y-auto">
                @forelse($outgoing as $p)
                    <div class="px-3 py-2 border-b text-sm flex justify-between gap-3 bg-rose-50/70 dark:bg-rose-900/20">
                        <span>
                            <a href="{{ route('cp.payments.show', $p) }}" class="font-medium">{{ $p->name }}</a>
                            <span class="text-slate-500"> — {{ format_date($p->occurred_on) }}@if($p->paymentMethod) · {{ $p->paymentMethod->name }}@endif@if($p->fund) · {{ $p->fund->name }}@endif</span>
                            @include('cp.partials.note-line', ['notes' => $p->notes])
                        </span>
                        <strong class="text-rose-600 whitespace-nowrap">{{ $p->currency->format($p->amount) }}</strong>
                    </div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا دفعات صادرة مطابقة للتصفية.</p>
                @endforelse
                @if($outgoingCount > $outgoing->count())
                    <p class="p-3 text-xs text-slate-500 text-center">يُعرض {{ $outgoing->count() }} من {{ $outgoingCount }}. التصدير يشمل الكل.</p>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
