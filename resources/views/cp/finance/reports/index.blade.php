@extends('cp.layout')
@section('title', 'التقارير')
@section('content')
<div class="space-y-8">
    <form class="flex flex-wrap gap-2 items-end">
        <div><label class="text-xs">من</label><input type="date" name="from" value="{{ $from }}" class="rounded-xl border px-3 py-2 dark:bg-slate-700 block"></div>
        <div><label class="text-xs">إلى</label><input type="date" name="to" value="{{ $to }}" class="rounded-xl border px-3 py-2 dark:bg-slate-700 block"></div>
        <button class="px-4 py-2 rounded-xl bg-primary text-white">تطبيق الفترة</button>
    </form>

    <section>
        <h2 class="font-bold text-lg mb-3">تقرير الأرصدة</h2>
        <div class="overflow-x-auto rounded-2xl border bg-white dark:bg-slate-800">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">الصندوق</th>@foreach($snapshot['currencies'] as $c)<th class="px-3 py-2">{{ $c->code }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($snapshot['funds'] as $fund)
                    <tr class="border-t"><td class="px-3 py-2 font-medium">{{ $fund->name }}</td>@foreach($snapshot['currencies'] as $c)<td class="px-3 py-2">{{ $c->format($snapshot['fundTotals'][$fund->id][$c->id] ?? 0) }}</td>@endforeach</tr>
                @endforeach
                <tr class="border-t font-bold bg-primary/5"><td class="px-3 py-2">الإجمالي</td>@foreach($snapshot['currencies'] as $c)<td class="px-3 py-2">{{ $c->format($snapshot['grand'][$c->id] ?? 0) }}</td>@endforeach</tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid md:grid-cols-{{ tenantBusinessEnabled() ? '3' : '2' }} gap-4">
        @if(tenantBusinessEnabled())
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-800">
            <h3 class="font-bold mb-2">مستحقات العملاء</h3>
            @foreach($snapshot['currencies'] as $c)<p>{{ $c->format($receivables[$c->id] ?? 0) }}</p>@endforeach
        </div>
        @endif
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-800">
            <h3 class="font-bold mb-2">ديوني للعائلة</h3>
            @foreach($snapshot['currencies'] as $c)<p>{{ $c->format($iOwe[$c->id] ?? 0) }}</p>@endforeach
        </div>
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-800">
            <h3 class="font-bold mb-2">مستحق لي من العائلة</h3>
            @foreach($snapshot['currencies'] as $c)<p>{{ $c->format($theyOwe[$c->id] ?? 0) }}</p>@endforeach
        </div>
    </section>

    @if(tenantBusinessEnabled())
    <section>
        <h2 class="font-bold text-lg mb-3">تقرير العملاء</h2>
        <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">العميل</th><th class="px-3 py-2">العملة</th><th class="px-3 py-2">الخدمات</th><th class="px-3 py-2">المدفوع</th><th class="px-3 py-2">المتبقي</th></tr></thead>
                <tbody class="divide-y dark:divide-slate-700">
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
        <h2 class="font-bold text-lg mb-3">تقرير العائلة — القروض المفتوحة</h2>
        <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-700/50"><tr><th class="px-3 py-2">الشخص</th><th class="px-3 py-2">الاتجاه</th><th class="px-3 py-2">المتبقي</th><th class="px-3 py-2">التاريخ</th></tr></thead>
                <tbody class="divide-y dark:divide-slate-700">
                @forelse($openLoans as $loan)
                    <tr>
                        <td class="px-3 py-2">{{ $loan->familyMember->name }}</td>
                        <td class="px-3 py-2">{{ $loan->direction->label() }}</td>
                        <td class="px-3 py-2">{{ $loan->currency->format($loan->remainingAmount()) }}</td>
                        <td class="px-3 py-2">{{ $loan->loan_date->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-6 text-center text-slate-500">لا قروض مفتوحة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid lg:grid-cols-{{ tenantBusinessEnabled() ? '2' : '1' }} gap-6">
        @if(tenantBusinessEnabled())
        <div>
            <h2 class="font-bold text-lg mb-3">إيرادات الفترة</h2>
            <div class="rounded-2xl border bg-white dark:bg-slate-800 max-h-96 overflow-y-auto">
                @forelse($revenue as $p)
                    <div class="px-3 py-2 border-b text-sm flex justify-between"><span>{{ $p->client->name }} — {{ $p->payment_date->format('Y-m-d') }}</span><strong>{{ $p->currency->format($p->amount) }}</strong></div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا إيرادات في الفترة.</p>
                @endforelse
            </div>
        </div>
        @endif
        <div>
            <h2 class="font-bold text-lg mb-3">مصروفات الفترة</h2>
            <div class="rounded-2xl border bg-white dark:bg-slate-800 max-h-96 overflow-y-auto">
                @forelse($expenses as $e)
                    <div class="px-3 py-2 border-b text-sm flex justify-between"><span>{{ $e->description }} — {{ $e->expense_date->format('Y-m-d') }}</span><strong>{{ $e->currency->format($e->amount) }}</strong></div>
                @empty
                    <p class="p-6 text-slate-500 text-sm">لا مصروفات في الفترة.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
