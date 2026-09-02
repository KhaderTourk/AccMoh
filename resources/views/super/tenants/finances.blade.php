@extends('super.layout')
@section('title', 'أرصدة — '.$tenant->name)
@section('content')
<div class="space-y-6">
    <div class="flex justify-between gap-3 flex-wrap items-center">
        <div>
            <a href="{{ route('super.tenants.show', $tenant) }}" class="text-sm text-emerald-700">← {{ $tenant->name }}</a>
            <h1 class="text-2xl font-extrabold mt-1">الأرصدة والبيانات</h1>
            <p class="text-sm text-slate-500">{{ $tenant->owner?->email }}</p>
        </div>
        <a href="{{ route('super.tenants.reports', $tenant) }}" class="px-4 py-2 rounded-xl border bg-white text-sm">التقارير</a>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @if($tenant->business_enabled)
        <div class="rounded-2xl bg-white border p-4"><p class="text-xs text-slate-500">عملاء</p><p class="text-2xl font-extrabold">{{ $counts['clients'] }}</p></div>
        @endif
        <div class="rounded-2xl bg-white border p-4"><p class="text-xs text-slate-500">الأفراد</p><p class="text-2xl font-extrabold">{{ $counts['family'] }}</p></div>
        <div class="rounded-2xl bg-white border p-4"><p class="text-xs text-slate-500">دائن/مدين مفتوح</p><p class="text-2xl font-extrabold">{{ $counts['open_loans'] }}</p></div>
        <div class="rounded-2xl bg-white border p-4"><p class="text-xs text-slate-500">مستخدمو النسخة</p><p class="text-2xl font-extrabold">{{ $counts['users'] }}</p></div>
    </div>

    <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="rounded-2xl bg-slate-900 text-white p-5">
            <p class="text-sm text-slate-300 mb-2">الرصيد الكلي</p>
            @foreach($snapshot['currencies'] as $c)
                <p class="text-xl font-extrabold">{{ $c->format($snapshot['grand'][$c->id] ?? 0) }}</p>
            @endforeach
        </div>
        @foreach($snapshot['funds'] as $fund)
        <div class="rounded-2xl bg-white border p-5">
            <p class="text-sm text-slate-500 mb-2">{{ $fund->name }}</p>
            @foreach($snapshot['currencies'] as $c)
                <p class="text-lg font-bold">{{ $c->format($snapshot['fundTotals'][$fund->id][$c->id] ?? 0) }}</p>
            @endforeach
        </div>
        @endforeach
    </section>

    <section>
        <h2 class="font-bold mb-3">توزيع طرق الدفع</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach($snapshot['methods'] as $method)
            <div class="rounded-2xl bg-white border p-4">
                <p class="font-bold mb-2">{{ $method->name }}</p>
                @foreach($snapshot['currencies'] as $c)
                    <p class="text-sm">{{ $c->format($snapshot['methodTotals'][$method->id][$c->id] ?? 0) }}</p>
                @endforeach
            </div>
            @endforeach
        </div>
    </section>

    <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @if($tenant->business_enabled)
        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4">
            <p class="font-bold text-amber-800 mb-2">مستحقات العملاء</p>
            @foreach($snapshot['currencies'] as $c)
                <p class="font-extrabold">{{ $c->format($receivables[$c->id] ?? 0) }}</p>
            @endforeach
        </div>
        @endif
        <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4">
            <p class="font-bold text-rose-800 mb-2">دائن (عليّ)</p>
            @foreach($snapshot['currencies'] as $c)
                <p class="font-extrabold">{{ $c->format($iOwe[$c->id] ?? 0) }}</p>
            @endforeach
        </div>
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
            <p class="font-bold text-emerald-800 mb-2">مدين (لي)</p>
            @foreach($snapshot['currencies'] as $c)
                <p class="font-extrabold">{{ $c->format($theyOwe[$c->id] ?? 0) }}</p>
            @endforeach
        </div>
    </section>

    <section class="grid lg:grid-cols-2 gap-6">
        <div class="rounded-2xl bg-white border overflow-hidden">
            <div class="px-4 py-3 border-b font-bold">دائن ومدين المفتوح</div>
            <div class="max-h-80 overflow-y-auto divide-y text-sm">
                @forelse($openLoans as $loan)
                    <div class="px-4 py-2 flex justify-between gap-2">
                        <span>{{ $loan->familyMember->name }} — {{ $loan->direction->label() }}</span>
                        <strong>{{ $loan->currency->format($loan->remainingAmount()) }}</strong>
                    </div>
                @empty
                    <p class="p-6 text-slate-500">لا حركات مفتوحة.</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-2xl bg-white border overflow-hidden">
            <div class="px-4 py-3 border-b font-bold">آخر الحركات</div>
            <div class="max-h-80 overflow-y-auto divide-y text-sm">
                @forelse($recent as $entry)
                    <div class="px-4 py-2 flex justify-between gap-2">
                        <div>
                            <p class="font-medium">{{ $entry->description }}</p>
                            @if($entry->notes)<p class="text-xs text-slate-500 whitespace-pre-line">{{ $entry->notes }}</p>@endif
                            <p class="text-xs text-slate-500">{{ $entry->occurred_on->format('Y-m-d') }} · {{ $entry->paymentMethod->name }}</p>
                        </div>
                        <strong class="{{ \App\Support\Money::isNegative($entry->amount) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $entry->currency->format($entry->amount) }}</strong>
                    </div>
                @empty
                    <p class="p-6 text-slate-500">لا حركات.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
