@extends('super.layout')
@section('title', 'تقارير — '.$tenant->name)
@section('content')
<div class="space-y-8">
    <div class="flex justify-between gap-3 flex-wrap items-center">
        <div>
            <a href="{{ route('super.tenants.show', $tenant) }}" class="text-sm text-emerald-700">← {{ $tenant->name }}</a>
            <h1 class="text-2xl font-extrabold mt-1">تقارير النسخة</h1>
        </div>
        <a href="{{ route('super.tenants.finances', $tenant) }}" class="px-4 py-2 rounded-xl border bg-white text-sm">الأرصدة</a>
    </div>

    <section>
        <h2 class="font-bold text-lg mb-3">الأرصدة حسب الدرج</h2>
        <div class="overflow-x-auto rounded-2xl border bg-white">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2">الدرج</th>@foreach($snapshot['currencies'] as $c)<th class="px-3 py-2">{{ $c->name }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($snapshot['funds'] as $fund)
                    <tr class="border-t"><td class="px-3 py-2 font-medium">{{ $fund->name }}</td>@foreach($snapshot['currencies'] as $c)<td class="px-3 py-2">{{ $c->format($snapshot['fundTotals'][$fund->id][$c->id] ?? 0) }}</td>@endforeach</tr>
                @endforeach
                <tr class="border-t font-bold bg-emerald-50"><td class="px-3 py-2">الإجمالي</td>@foreach($snapshot['currencies'] as $c)<td class="px-3 py-2">{{ $c->format($snapshot['grand'][$c->id] ?? 0) }}</td>@endforeach</tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @if($tenant->business_enabled)
        <div class="rounded-2xl border p-4 bg-white">
            <h3 class="font-bold mb-2">مستحقات الزبائن</h3>
            @foreach($snapshot['currencies'] as $c)<p>{{ $c->format($receivables[$c->id] ?? 0) }}</p>@endforeach
        </div>
        @endif
        <div class="rounded-2xl border p-4 bg-white">
            <h3 class="font-bold mb-2">صافي دفعات الأشخاص</h3>
            @foreach($snapshot['currencies'] as $c)
                @php $net = $personNet[$c->id] ?? '0'; @endphp
                <p class="{{ \App\Support\Money::isNegative($net) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $c->format($net) }}</p>
            @endforeach
        </div>
    </section>

    @if($tenant->business_enabled)
    <section>
        <h2 class="font-bold text-lg mb-3">تقرير الزبائن</h2>
        <div class="rounded-2xl border bg-white overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2">الزبون</th><th class="px-3 py-2">العملة</th><th class="px-3 py-2">الخدمات</th><th class="px-3 py-2">المدفوع</th><th class="px-3 py-2">المتبقي</th></tr></thead>
                <tbody class="divide-y">
                @forelse($clientSummary as $row)
                    @foreach($row['rows'] as $r)
                    <tr>
                        <td class="px-3 py-2">{{ $row['client']->name }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->name }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->format($r['billed']) }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->format($r['paid']) }}</td>
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

    <section>
        <h2 class="font-bold text-lg mb-3">الأشخاص</h2>
        <div class="rounded-2xl border bg-white overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2">الشخص</th><th class="px-3 py-2">العملة</th><th class="px-3 py-2">وارد</th><th class="px-3 py-2">صادر</th></tr></thead>
                <tbody class="divide-y">
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
            <h2 class="font-bold text-lg mb-3">آخر الدفعات الواردة</h2>
            <div class="rounded-2xl border bg-white max-h-80 overflow-y-auto">
                @forelse($incoming as $p)
                    <div class="px-3 py-2 border-b text-sm flex justify-between gap-3 bg-emerald-50">
                        <span>{{ $p->name }} — {{ $p->occurred_on->format('Y-m-d') }}</span>
                        <strong class="text-emerald-700">{{ $p->currency->format($p->amount) }}</strong>
                    </div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا دفعات واردة.</p>
                @endforelse
            </div>
        </div>
        <div>
            <h2 class="font-bold text-lg mb-3">آخر الدفعات الصادرة</h2>
            <div class="rounded-2xl border bg-white max-h-80 overflow-y-auto">
                @forelse($outgoing as $p)
                    <div class="px-3 py-2 border-b text-sm flex justify-between gap-3 bg-rose-50">
                        <span>{{ $p->name }} — {{ $p->occurred_on->format('Y-m-d') }}</span>
                        <strong class="text-rose-700">{{ $p->currency->format($p->amount) }}</strong>
                    </div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا دفعات صادرة.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
