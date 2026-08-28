@extends('cp.layout')

@section('title', 'لوحة التحكم المالية')

@section('content')
<div class="space-y-8">
    {{-- Quick actions --}}
    <section class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-9 gap-3">
        <a href="{{ route('cp.offline') }}" class="rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 p-3 text-center hover:shadow-md transition-all group">
            <span class="material-symbols-outlined text-amber-600 text-2xl group-hover:scale-110 transition-transform">cloud_off</span>
            <p class="text-xs font-medium mt-1 text-amber-800 dark:text-amber-300">وضع Offline</p>
        </a>
        @foreach([
            ['route' => 'cp.clients.create', 'icon' => 'person_add', 'label' => 'عميل', 'business' => true],
            ['route' => 'cp.family-members.create', 'icon' => 'group_add', 'label' => 'فرد', 'business' => false],
            ['route' => 'cp.client-services.create', 'icon' => 'work', 'label' => 'خدمة', 'business' => true],
            ['route' => 'cp.payments.create', 'icon' => 'payments', 'label' => 'دفعة', 'business' => true],
            ['route' => 'cp.family-loans.create', 'params' => ['direction' => 'lent'], 'icon' => 'handshake', 'label' => 'مدين', 'business' => false],
            ['route' => 'cp.family-loans.create', 'params' => ['direction' => 'borrowed'], 'icon' => 'replay', 'label' => 'دائن', 'business' => false],
            ['route' => 'cp.expenses.create', 'icon' => 'receipt_long', 'label' => 'مصروف', 'business' => false],
            ['route' => 'cp.transfers.create', 'icon' => 'swap_horiz', 'label' => 'تحويل', 'business' => false],
        ] as $action)
            @if(empty($action['business']) || tenantBusinessEnabled())
            <a href="{{ route($action['route'], $action['params'] ?? []) }}" class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-3 text-center hover:border-primary/40 hover:shadow-md transition-all group">
                <span class="material-symbols-outlined text-primary text-2xl group-hover:scale-110 transition-transform">{{ $action['icon'] }}</span>
                <p class="text-xs font-medium mt-1 text-slate-600 dark:text-slate-300">{{ $action['label'] }}</p>
            </a>
            @endif
        @endforeach
    </section>

    {{-- Balance cards --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 text-white p-6 shadow-lg">
            <p class="text-sm text-slate-300 mb-3">الرصيد الكلي</p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="text-2xl font-extrabold mb-1">{{ $currency->format($snapshot['grand'][$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
        @foreach($snapshot['funds'] as $fund)
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
            <p class="text-sm text-slate-500 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">{{ $fund->slug === 'family' ? 'person' : 'business_center' }}</span>
                {{ $fund->name }}
            </p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="text-xl font-bold text-slate-800 dark:text-white mb-1">{{ $currency->format($snapshot['fundTotals'][$fund->id][$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
        @endforeach
    </section>

    {{-- Methods --}}
    <section>
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2"><span class="material-symbols-outlined text-primary">account_balance_wallet</span> توزيع طرق الدفع</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($snapshot['methods'] as $method)
            <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-primary">{{ $method->icon ?: 'payments' }}</span>
                    <span class="font-bold">{{ $method->name }}</span>
                </div>
                @foreach($snapshot['currencies'] as $currency)
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $currency->format($snapshot['methodTotals'][$method->id][$currency->id] ?? 0) }}</p>
                @endforeach
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 space-y-1 text-xs text-slate-500">
                    @foreach($snapshot['funds'] as $fund)
                        <p>{{ $fund->name }}:
                            @foreach($snapshot['currencies'] as $currency)
                                {{ $currency->code }} {{ number_format((float)($snapshot['cells'][$fund->id][$method->id][$currency->id] ?? 0), 2) }}@if(!$loop->last)، @endif
                            @endforeach
                        </p>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- Receivables --}}
    <section class="grid grid-cols-1 md:grid-cols-{{ tenantBusinessEnabled() ? '3' : '2' }} gap-4">
        @if(tenantBusinessEnabled())
        <div class="rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 p-5">
            <p class="font-bold text-amber-800 dark:text-amber-300 mb-2">مستحقات العملاء</p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="text-lg font-extrabold">{{ $currency->format($receivables[$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
        @endif
        <div class="rounded-2xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/40 p-5">
            <p class="font-bold text-rose-800 dark:text-rose-300 mb-2">دائن (عليّ)</p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="text-lg font-extrabold">{{ $currency->format($iOwe[$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
        <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/40 p-5">
            <p class="font-bold text-emerald-800 dark:text-emerald-300 mb-2">مدين (لي)</p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="text-lg font-extrabold">{{ $currency->format($theyOwe[$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-2 lg:grid-cols-{{ tenantBusinessEnabled() ? '4' : '2' }} gap-4">
        @if(tenantBusinessEnabled())
        <div class="rounded-xl bg-white dark:bg-slate-800 border p-4"><p class="text-xs text-slate-500">العملاء</p><p class="text-2xl font-bold">{{ $counts['clients'] }}</p></div>
        @endif
        <div class="rounded-xl bg-white dark:bg-slate-800 border p-4"><p class="text-xs text-slate-500">الأفراد</p><p class="text-2xl font-bold">{{ $counts['family'] }}</p></div>
        @if(tenantBusinessEnabled())
        <div class="rounded-xl bg-white dark:bg-slate-800 border p-4"><p class="text-xs text-slate-500">الخدمات</p><p class="text-2xl font-bold">{{ $counts['open_services'] }}</p></div>
        @endif
        <div class="rounded-xl bg-white dark:bg-slate-800 border p-4"><p class="text-xs text-slate-500">دائن/مدين مفتوح</p><p class="text-2xl font-bold">{{ $counts['open_loans'] }}</p></div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl bg-white dark:bg-slate-800 border p-5">
            <h3 class="font-bold mb-4">الإيرادات والمصروفات (آخر 6 أشهر)</h3>
            <canvas id="revExpChart" height="160"></canvas>
        </div>
        <div class="rounded-2xl bg-white dark:bg-slate-800 border p-5">
            <h3 class="font-bold mb-4">توزيع الأرصدة حسب طريقة الدفع (شيكل)</h3>
            <canvas id="methodsChart" height="160"></canvas>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-{{ tenantBusinessEnabled() ? '2' : '1' }} gap-6">
        @if(tenantBusinessEnabled())
        <div class="rounded-2xl bg-white dark:bg-slate-800 border p-5">
            <h3 class="font-bold mb-4">العملاء الأكثر مديونية</h3>
            @forelse($topIndebted as $row)
                <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-700 text-sm">
                    <a href="{{ route('cp.clients.show', $row['client']) }}" class="text-primary font-medium">{{ $row['client']->name }}</a>
                    <span>
                        @foreach($snapshot['currencies'] as $currency)
                            @if(\App\Support\Money::isPositive($row['by_currency'][$currency->id] ?? 0))
                                {{ $currency->format($row['by_currency'][$currency->id]) }}
                            @endif
                        @endforeach
                    </span>
                </div>
            @empty
                <p class="text-slate-500 text-sm">لا توجد مستحقات حالياً.</p>
            @endforelse
        </div>
        @endif
        <div class="rounded-2xl bg-white dark:bg-slate-800 border p-5">
            <h3 class="font-bold mb-4">آخر الحركات المالية</h3>
            <div class="space-y-2 max-h-80 overflow-y-auto">
                @forelse($recent as $entry)
                <div class="flex items-start justify-between gap-3 text-sm py-2 border-b border-slate-100 dark:border-slate-700">
                    <div>
                        <p class="font-medium">{{ $entry->description }}</p>
                        <p class="text-xs text-slate-500">{{ $entry->transaction_type->label() }} · {{ $entry->paymentMethod->name }} · {{ $entry->occurred_on->format('Y-m-d') }}</p>
                    </div>
                    <span class="font-bold {{ \App\Support\Money::isNegative($entry->amount) ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $entry->currency->format($entry->amount) }}
                    </span>
                </div>
                @empty
                <p class="text-slate-500 text-sm">لا توجد حركات بعد. ابدأ برصيد افتتاحي أو عملية مالية.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const monthKeys = @json($monthKeys);
const revenue = @json($revenueByMonth);
const expenses = @json($expenseByMonth);
const methodDist = @json($methodDistribution);
const currencyCodes = @json($snapshot['currencies']->pluck('code')->values());
const chartColors = ['#08A46D', '#e11d48', '#0ea5e9', '#f59e0b', '#8b5cf6', '#14b8a6'];
const revExpDatasets = [];
currencyCodes.forEach((code, i) => {
    revExpDatasets.push({
        label: 'إيرادات ' + code,
        data: monthKeys.map(k => revenue[code]?.[k] ?? 0),
        borderColor: chartColors[(i * 2) % chartColors.length],
        tension: .3
    });
    revExpDatasets.push({
        label: 'مصروفات ' + code,
        data: monthKeys.map(k => expenses[code]?.[k] ?? 0),
        borderColor: chartColors[(i * 2 + 1) % chartColors.length],
        tension: .3
    });
});

new Chart(document.getElementById('revExpChart'), {
    type: 'line',
    data: { labels: monthKeys, datasets: revExpDatasets },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('methodsChart'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(methodDist),
        datasets: [{
            data: Object.values(methodDist).map(v => v.ILS ?? 0),
            backgroundColor: ['#08A46D', '#0ea5e9', '#8b5cf6', '#f59e0b']
        }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});
</script>
@endpush
