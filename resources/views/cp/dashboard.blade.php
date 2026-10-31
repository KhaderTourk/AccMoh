@extends('cp.layout')

@section('title', 'لوحة التحكم المالية')

@section('content')
<div class="space-y-6">
    @include('cp.partials.subs-banner')
    <section class="cp-welcome">
        <div>
            <p class="cp-welcome-date" data-today></p>
            <h2 class="cp-welcome-title">مرحباً{{ auth()->user() ? '، '.auth()->user()->name : '' }}</h2>
            <p class="cp-welcome-sub">هذه نظرة سريعة على وضعك المالي اليوم.</p>
        </div>
        <a href="{{ route('cp.offline') }}" class="cp-chip-offline">
            <span class="material-symbols-outlined">cloud_off</span>
            <span>وضع Offline</span>
        </a>
    </section>

    <section class="cp-quick-grid">
        @foreach([
            ['route' => 'cp.payments.create', 'params' => ['incoming'], 'icon' => 'south_west', 'label' => 'دفعة واردة', 'tone' => 'in', 'business' => false],
            ['route' => 'cp.payments.create', 'params' => ['outgoing'], 'icon' => 'north_east', 'label' => 'دفعة صادرة', 'tone' => 'out', 'business' => false],
            ['route' => 'cp.transfers.create', 'icon' => 'swap_horiz', 'label' => 'تحويل', 'tone' => 'transfer', 'business' => false],
            ['route' => 'cp.clients.create', 'icon' => 'person_add', 'label' => 'زبون', 'tone' => 'brand', 'business' => true],
            ['route' => 'cp.persons.create', 'icon' => 'group_add', 'label' => 'شخص', 'tone' => 'violet', 'business' => false],
            ['route' => 'cp.client-services.create', 'icon' => 'work', 'label' => 'خدمة', 'tone' => 'amber', 'business' => true],
        ] as $action)
            @if(empty($action['business']) || tenantBusinessEnabled())
            <a href="{{ route($action['route'], $action['params'] ?? []) }}" class="cp-quick cp-tone-{{ $action['tone'] }}">
                <span class="cp-quick-icon"><span class="material-symbols-outlined">{{ $action['icon'] }}</span></span>
                <span class="cp-quick-label">{{ $action['label'] }}</span>
            </a>
            @endif
        @endforeach
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="cp-hero-card">
            <div class="cp-hero-glow" aria-hidden="true"></div>
            <p class="cp-hero-label"><span class="material-symbols-outlined">account_balance</span> الرصيد الكلي</p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="cp-hero-amount" data-countup>{{ $currency->format($snapshot['grand'][$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
        @foreach($snapshot['funds'] as $fund)
        <div class="cp-stat-card">
            <p class="cp-stat-label">
                <span class="cp-stat-icon"><span class="material-symbols-outlined">{{ $fund->slug === 'family' ? 'person' : 'business_center' }}</span></span>
                {{ $fund->name }}
            </p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="cp-stat-amount" data-countup>{{ $currency->format($snapshot['fundTotals'][$fund->id][$currency->id] ?? 0) }}</p>
            @endforeach
            <div class="mt-4 flex gap-2">
                <a href="{{ route('cp.payments.create', ['incoming', 'fund_id' => $fund->id]) }}" class="cp-mini-btn cp-mini-in"><span class="material-symbols-outlined">south_west</span> واردة</a>
                <a href="{{ route('cp.payments.create', ['outgoing', 'fund_id' => $fund->id]) }}" class="cp-mini-btn cp-mini-out"><span class="material-symbols-outlined">north_east</span> صادرة</a>
            </div>
        </div>
        @endforeach
    </section>

    <section>
        <h3 class="cp-section-title"><span class="material-symbols-outlined">account_balance_wallet</span> توزيع طرق الدفع</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($snapshot['methods'] as $method)
            <div class="cp-stat-card cp-stat-compact">
                <div class="flex items-center gap-2 mb-3">
                    <span class="cp-stat-icon"><span class="material-symbols-outlined">{{ $method->icon ?: 'payments' }}</span></span>
                    <span class="font-bold">{{ $method->name }}</span>
                </div>
                @foreach($snapshot['currencies'] as $currency)
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-200" data-countup>{{ $currency->format($snapshot['methodTotals'][$method->id][$currency->id] ?? 0) }}</p>
                @endforeach
            </div>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-{{ tenantBusinessEnabled() ? '2' : '1' }} {{ tenantBusinessEnabled() ? 'lg:grid-cols-4' : '' }} gap-4">
        @if(tenantBusinessEnabled())
        <div class="cp-stat-card cp-accent-amber">
            <p class="cp-stat-label"><span class="cp-stat-icon"><span class="material-symbols-outlined">request_quote</span></span> مستحق على الزبائن</p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="cp-stat-amount" data-countup>{{ $currency->format($receivables[$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
        <div class="cp-stat-card cp-accent-orange">
            <p class="cp-stat-label"><span class="cp-stat-icon"><span class="material-symbols-outlined">badge</span></span> مستحقات الموظفين</p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="cp-stat-amount text-amber-700 dark:text-amber-300" data-countup>{{ $currency->format($workerPayables[$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
        <div class="cp-stat-card cp-accent-sky">
            <p class="cp-stat-label"><span class="cp-stat-icon"><span class="material-symbols-outlined">local_shipping</span></span> مستحقات الموردين</p>
            @foreach($snapshot['currencies'] as $currency)
                <p class="cp-stat-amount text-sky-700 dark:text-sky-300" data-countup>{{ $currency->format($supplierPayables[$currency->id] ?? 0) }}</p>
            @endforeach
        </div>
        @endif
        <div class="cp-stat-card cp-accent-violet">
            <p class="cp-stat-label"><span class="cp-stat-icon"><span class="material-symbols-outlined">diversity_3</span></span> موقف الأشخاص (عليه / له)</p>
            @foreach($snapshot['currencies'] as $currency)
                @php $net = $personNet[$currency->id] ?? '0'; @endphp
                <p class="cp-stat-amount {{ \App\Support\Money::isNegative($net) ? 'text-rose-600' : 'text-emerald-600' }}" data-countup>{{ $currency->format($net) }}</p>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-2 lg:grid-cols-{{ tenantBusinessEnabled() ? '3' : '1' }} gap-4">
        @if(tenantBusinessEnabled())
        <div class="cp-count-card"><span class="cp-count-icon"><span class="material-symbols-outlined">groups</span></span><div><p class="cp-count-label">الزبائن</p><p class="cp-count-value" data-countup>{{ $counts['clients'] }}</p></div></div>
        @endif
        <div class="cp-count-card"><span class="cp-count-icon"><span class="material-symbols-outlined">person</span></span><div><p class="cp-count-label">الأشخاص</p><p class="cp-count-value" data-countup>{{ $counts['persons'] }}</p></div></div>
        @if(tenantBusinessEnabled())
        <div class="cp-count-card"><span class="cp-count-icon"><span class="material-symbols-outlined">work</span></span><div><p class="cp-count-label">الخدمات</p><p class="cp-count-value" data-countup>{{ $counts['open_services'] }}</p></div></div>
        @endif
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="cp-panel">
            <h3 class="cp-panel-title"><span class="material-symbols-outlined">monitoring</span> الوارد والصادر (آخر 6 أشهر)</h3>
            <canvas id="revExpChart" height="160"></canvas>
        </div>
        <div class="cp-panel">
            <h3 class="cp-panel-title"><span class="material-symbols-outlined">donut_large</span> توزيع الأرصدة حسب طريقة الدفع (شيكل)</h3>
            <canvas id="methodsChart" height="160"></canvas>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-{{ tenantBusinessEnabled() ? '2' : '1' }} gap-6">
        @if(tenantBusinessEnabled())
        <div class="cp-panel">
            <h3 class="cp-panel-title"><span class="material-symbols-outlined">trending_up</span> الزبائن الأكثر مديونية</h3>
            @forelse($topIndebted as $row)
                <div class="cp-list-row">
                    <a href="{{ route('cp.clients.show', $row['client']) }}" class="cp-list-name">
                        <span class="cp-list-avatar">{{ mb_substr($row['client']->personName(), 0, 1) }}</span>
                        {{ $row['client']->personName() }}
                    </a>
                    <span class="font-bold text-amber-700 dark:text-amber-300">
                        @foreach($snapshot['currencies'] as $currency)
                            @if(\App\Support\Money::isPositive($row['by_currency'][$currency->id] ?? 0))
                                {{ $currency->format($row['by_currency'][$currency->id]) }}
                            @endif
                        @endforeach
                    </span>
                </div>
            @empty
                <div class="cp-empty-state">
                    <span class="material-symbols-outlined">task_alt</span>
                    <p>لا توجد مستحقات حالياً.</p>
                </div>
            @endforelse
        </div>
        @endif
        <div class="cp-panel">
            <h3 class="cp-panel-title"><span class="material-symbols-outlined">receipt_long</span> آخر الحركات المالية</h3>
            <div class="max-h-80 overflow-y-auto -mx-1 px-1">
                @forelse($recent as $entry)
                <div class="cp-list-row items-start">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="cp-entry-dot {{ \App\Support\Money::isNegative($entry->amount) ? 'is-out' : 'is-in' }}">
                            <span class="material-symbols-outlined">{{ \App\Support\Money::isNegative($entry->amount) ? 'north_east' : 'south_west' }}</span>
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium">{{ $entry->description }}</p>
                            @include('cp.partials.note-line', ['notes' => $entry->notes])
                            <p class="text-xs text-slate-500">{{ $entry->transaction_type->label() }} · {{ format_date($entry->occurred_on) }} · {{ $entry->created_at?->format('H:i') }}</p>
                        </div>
                    </div>
                    <span class="font-bold whitespace-nowrap {{ \App\Support\Money::isNegative($entry->amount) ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $entry->currency->format($entry->amount) }}
                    </span>
                </div>
                @empty
                <div class="cp-empty-state">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <p>لا توجد حركات بعد. ابدأ برصيد افتتاحي أو عملية مالية.</p>
                </div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var today = document.querySelector('[data-today]');
    if (today) {
        try {
            today.textContent = new Intl.DateTimeFormat('ar', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());
        } catch (e) {}
    }
})();

const monthKeys = @json($monthKeys);
const revenue = @json($revenueByMonth);
const expenses = @json($expenseByMonth);
const methodDist = @json($methodDistribution);
const currencyCodes = @json($snapshot['currencies']->pluck('code')->values());
const chartColors = ['#FB005B', '#e11d48', '#0ea5e9', '#f59e0b', '#8b5cf6', '#14b8a6'];

function cpChartTheme() {
    const dark = document.documentElement.classList.contains('dark');
    Chart.defaults.font.family = 'Tajawal, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = dark ? '#8393ab' : '#64748b';
    Chart.defaults.borderColor = dark ? 'rgba(148,163,184,0.12)' : 'rgba(15,23,42,0.06)';
    Chart.defaults.plugins.tooltip.backgroundColor = dark ? '#16213a' : '#0f172a';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.plugins.tooltip.titleFont = { family: 'Tajawal', weight: '700' };
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
}

function cpFade(ctx, hex) {
    const g = ctx.createLinearGradient(0, 0, 0, 260);
    g.addColorStop(0, hex + '40');
    g.addColorStop(1, hex + '00');
    return g;
}

if (window.Chart) {
    cpChartTheme();
    const revCanvas = document.getElementById('revExpChart');
    const revCtx = revCanvas.getContext('2d');
    const revExpDatasets = [];
    currencyCodes.forEach((code, i) => {
        const inColor = chartColors[(i * 2) % chartColors.length];
        const outColor = chartColors[(i * 2 + 1) % chartColors.length];
        revExpDatasets.push({
            label: 'وارد ' + code,
            data: monthKeys.map(k => revenue[code]?.[k] ?? 0),
            borderColor: inColor,
            backgroundColor: cpFade(revCtx, inColor),
            fill: true,
            borderWidth: 2.5,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: inColor,
            tension: .35
        });
        revExpDatasets.push({
            label: 'صادر ' + code,
            data: monthKeys.map(k => expenses[code]?.[k] ?? 0),
            borderColor: outColor,
            backgroundColor: cpFade(revCtx, outColor),
            fill: true,
            borderWidth: 2.5,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: outColor,
            tension: .35
        });
    });

    new Chart(revCanvas, {
        type: 'line',
        data: { labels: monthKeys, datasets: revExpDatasets },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            animation: { duration: 1100, easing: 'easeOutQuart' },
            plugins: { legend: { position: 'bottom' } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('methodsChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(methodDist),
            datasets: [{
                data: Object.values(methodDist).map(v => v.ILS ?? 0),
                backgroundColor: ['#FB005B', '#0ea5e9', '#8b5cf6', '#f59e0b'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            cutout: '68%',
            animation: { animateRotate: true, duration: 1200, easing: 'easeOutQuart' },
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>
@endpush
