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
        <h2 class="font-bold text-lg mb-3">الأرصدة حسب الصندوق</h2>
        <div class="overflow-x-auto rounded-2xl border bg-white">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2">الصندوق</th>@foreach($snapshot['currencies'] as $c)<th class="px-3 py-2">{{ $c->code }}</th>@endforeach</tr></thead>
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
            <h3 class="font-bold mb-2">مستحقات العملاء</h3>
            @foreach($snapshot['currencies'] as $c)<p>{{ $c->format($receivables[$c->id] ?? 0) }}</p>@endforeach
        </div>
        @endif
        <div class="rounded-2xl border p-4 bg-white">
            <h3 class="font-bold mb-2">دائن (عليّ)</h3>
            @foreach($snapshot['currencies'] as $c)<p>{{ $c->format($iOwe[$c->id] ?? 0) }}</p>@endforeach
        </div>
        <div class="rounded-2xl border p-4 bg-white">
            <h3 class="font-bold mb-2">مدين (لي)</h3>
            @foreach($snapshot['currencies'] as $c)<p>{{ $c->format($theyOwe[$c->id] ?? 0) }}</p>@endforeach
        </div>
    </section>

    @if($tenant->business_enabled)
    <section>
        <h2 class="font-bold text-lg mb-3">تقرير العملاء</h2>
        <div class="rounded-2xl border bg-white overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2">العميل</th><th class="px-3 py-2">العملة</th><th class="px-3 py-2">الخدمات</th><th class="px-3 py-2">المدفوع</th><th class="px-3 py-2">المتبقي</th></tr></thead>
                <tbody class="divide-y">
                @forelse($clientSummary as $row)
                    @foreach($row['rows'] as $r)
                    <tr>
                        <td class="px-3 py-2">{{ $row['client']->name }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->code }}</td>
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
        <h2 class="font-bold text-lg mb-3">دائن ومدين</h2>
        <div class="rounded-2xl border bg-white overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2">الشخص</th><th class="px-3 py-2">العملة</th><th class="px-3 py-2">دائن</th><th class="px-3 py-2">مدين</th></tr></thead>
                <tbody class="divide-y">
                @forelse($familySummary as $row)
                    @foreach($row['rows'] as $r)
                    <tr>
                        <td class="px-3 py-2">{{ $row['member']->name }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->code }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->format($r['owe']) }}</td>
                        <td class="px-3 py-2">{{ $r['currency']->format($r['owed']) }}</td>
                    </tr>
                    @endforeach
                @empty
                    <tr><td colspan="4" class="p-6 text-center text-slate-500">لا بيانات.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="font-bold text-lg mb-3">دائن ومدين المفتوح</h2>
        <div class="rounded-2xl border bg-white overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2">الشخص</th><th class="px-3 py-2">النوع</th><th class="px-3 py-2">المتبقي</th><th class="px-3 py-2">التاريخ</th></tr></thead>
                <tbody class="divide-y">
                @forelse($openLoans as $loan)
                    <tr>
                        <td class="px-3 py-2">{{ $loan->familyMember->name }}</td>
                        <td class="px-3 py-2">{{ $loan->direction->label() }}</td>
                        <td class="px-3 py-2">{{ $loan->currency->format($loan->remainingAmount()) }}</td>
                        <td class="px-3 py-2">{{ $loan->loan_date->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-6 text-center text-slate-500">لا حركات مفتوحة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid lg:grid-cols-2 gap-6">
        @if($tenant->business_enabled)
        <div>
            <h2 class="font-bold text-lg mb-3">آخر الإيرادات</h2>
            <div class="rounded-2xl border bg-white max-h-80 overflow-y-auto">
                @forelse($revenue as $p)
                    <div class="px-3 py-2 border-b text-sm flex justify-between"><span>{{ $p->client->name }} — {{ $p->payment_date->format('Y-m-d') }}</span><strong>{{ $p->currency->format($p->amount) }}</strong></div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا إيرادات.</p>
                @endforelse
            </div>
        </div>
        @endif
        <div>
            <h2 class="font-bold text-lg mb-3">آخر المصروفات</h2>
            <div class="rounded-2xl border bg-white max-h-80 overflow-y-auto">
                @forelse($expenses as $e)
                    <div class="px-3 py-2 border-b text-sm flex justify-between"><span>{{ $e->description }} — {{ $e->expense_date->format('Y-m-d') }}</span><strong>{{ $e->currency->format($e->amount) }}</strong></div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا مصروفات.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
