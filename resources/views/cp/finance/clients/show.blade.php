@extends('cp.layout')
@section('title', $client->personName())
@section('content')
@php
    $filterCount = collect(['from', 'to', '_preset'])->filter(fn ($key) => filled(request($key)))->count();
    $periodQuery = \App\Support\DateRange::queryParams($from ?? null, $to ?? null);
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold">{{ $client->personName() }}</h2>
            <p class="text-slate-500 text-sm">
                @if($client->organization()){{ $client->organization() }} · @endif
                {{ $client->phone }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cp.client-services.create', ['client_id' => $client->id]) }}" class="cp-btn cp-btn-primary"><span class="material-symbols-outlined">work</span> خدمة</a>
            <a href="{{ route('cp.payments.create', ['incoming', 'client_id' => $client->id]) }}" class="cp-btn cp-btn-in"><span class="material-symbols-outlined">south_west</span> دفعة واردة</a>
            <a href="{{ route('cp.clients.edit', $client) }}" class="cp-btn cp-btn-ghost"><span class="material-symbols-outlined">edit</span> تعديل</a>
        </div>
    </div>

    @component('cp.partials.filter-panel', ['count' => $filterCount, 'open' => true])
        @slot('actions')
            @unless($hasOpening)
                <a href="{{ route('cp.clients.export-pdf', array_merge(['client' => $client], $periodQuery)) }}" class="cp-btn cp-btn-ghost">
                    <span class="material-symbols-outlined">picture_as_pdf</span> تصدير PDF
                </a>
            @endunless
        @endslot
        @include('cp.partials.date-range-fields')
        @slot('footer')
            @include('cp.partials.date-range-shortcuts')
        @endslot
    @endcomponent

    @if($from || $to)
        <div class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm flex flex-wrap justify-between gap-2">
            <span>عرض حركات: <strong>{{ $periodLabel }}</strong> — {{ $movementCount }} حركة في الفترة.</span>
        </div>
    @endif

    @include('cp.partials.note-card', ['notes' => $client->notes])

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($summaries as $row)
            @php $currency = $row['currency']; @endphp
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
                <h3 class="font-bold mb-3">{{ $currency->name }}</h3>
                <div class="space-y-1 text-sm">
                    @if($hasOpening)
                        <p>رصيد سابق قبل الفترة:
                            <strong class="{{ \App\Support\Money::isNegative($row['computed_opening']) ? 'text-emerald-600' : (\App\Support\Money::isPositive($row['computed_opening']) ? 'text-amber-600' : '') }}">
                                @if(\App\Support\Money::isNegative($row['computed_opening']))
                                    عربون {{ $currency->format(\App\Support\Money::abs($row['computed_opening'])) }}
                                @else
                                    {{ $currency->format($row['computed_opening']) }}
                                @endif
                            </strong>
                        </p>
                    @endif
                    <p>قيمة الخدمات{{ $from || $to ? ' في الفترة' : '' }}: <strong>{{ $currency->format($row['billed']) }}</strong></p>
                    <p>المدفوع{{ $from || $to ? ' في الفترة' : '' }}: <strong class="text-emerald-600">{{ $currency->format($row['paid']) }}</strong></p>
                    @if(\App\Support\Money::isNegative($row['closing']))
                        <p>{{ $hasOpening ? 'المتبقي بعد الفترة' : 'المتبقي' }}: <strong class="text-emerald-600">عربون {{ $currency->format(\App\Support\Money::abs($row['closing'])) }}</strong></p>
                    @else
                        <p>{{ $hasOpening ? 'المتبقي بعد الفترة' : 'المتبقي' }}: <strong class="text-amber-600">{{ $currency->format($row['closing']) }}</strong></p>
                    @endif
                    @if($hasOpening && \App\Support\Money::cmp($row['closing'], $row['lifetime_due']) !== 0)
                        <p class="text-xs text-slate-500 pt-1">الرصيد الفعلي الحالي في النظام: {{ $currency->format($row['lifetime_due']) }}</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-8 text-center text-slate-500 md:col-span-2">لا أرصدة أو حركات{{ $from || $to ? ' في هذه الفترة' : '' }}.</div>
        @endforelse
    </div>

    @if($hasOpening)
        <form method="get" action="{{ route('cp.clients.export-pdf', $client) }}" class="rounded-2xl border border-amber-200 dark:border-amber-800/50 bg-amber-50/70 dark:bg-amber-900/15 p-5 space-y-3">
            @if($from)<input type="hidden" name="from" value="{{ $from }}">@endif
            @if($to)<input type="hidden" name="to" value="{{ $to }}">@endif
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-bold">تصدير كشف الفترة</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">يمكن تعديل الرصيد السابق للعرض في ملف PDF فقط. التعديل لا يُحفظ ولا يغيّر الأرصدة المسجّلة في النظام.</p>
                </div>
                <button class="cp-btn cp-btn-ghost"><span class="material-symbols-outlined">picture_as_pdf</span> تصدير PDF</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($summaries as $row)
                    <div>
                        <label class="text-xs block mb-0.5 text-slate-500">رصيد سابق ({{ $row['currency']->name }})</label>
                        <input type="number" step="0.01" name="opening[{{ $row['currency']->id }}]" value="{{ $row['computed_opening'] }}" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    </div>
                @endforeach
            </div>
        </form>
    @endif

    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-bold text-lg">الخدمات</h3>
            <a href="{{ route('cp.client-services.create', ['client_id' => $client->id]) }}" class="text-sm text-primary">إضافة خدمة</a>
        </div>
        @forelse($serviceGroups as $group)
            <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
                <div class="px-4 py-3 border-b bg-primary/5 dark:bg-primary/10 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-xs text-primary font-medium">نوع الخدمة</p>
                        <h4 class="font-bold">{{ $group['name'] }}</h4>
                    </div>
                    <div class="text-sm font-bold">
                        @foreach($group['totals'] as $total)
                            <span>{{ $total['formatted'] }}</span>@if(! $loop->last) · @endif
                        @endforeach
                    </div>
                </div>
                <table class="w-full text-sm text-right">
                    <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                        <th class="px-3 py-2">تفاصيل الخدمة</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y dark:divide-slate-700">
                    @foreach($group['services'] as $service)
                        <tr>
                            <td class="px-3 py-2">
                                {{ $service->title }}
                                @include('cp.partials.note-line', ['notes' => $service->notes])
                            </td>
                            <td class="px-3 py-2">
                                {{ $service->currency->format($service->amount) }}
                                @if($service->isFx())
                                    <div class="text-xs text-slate-500">{{ $service->fxCurrency?->format($service->source_amount) }} × {{ $service->formattedExchangeRate() }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ format_date($service->service_date) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1 justify-end">
                                    <a href="{{ route('cp.client-services.edit', $service) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                                    <form method="post" action="{{ route('cp.client-services.destroy', $service) }}" onsubmit="return confirm('حذف هذه الخدمة؟')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-8 text-center text-slate-500">لا توجد خدمات{{ $from || $to ? ' في هذه الفترة' : '' }}.</div>
        @endforelse
    </section>

    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-bold text-lg">الدفعات</h3>
            <a href="{{ route('cp.payments.create', ['incoming', 'client_id' => $client->id]) }}" class="text-sm text-primary">إضافة دفعة</a>
        </div>
        @forelse($paymentGroups as $group)
            <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
                <div class="px-4 py-3 border-b bg-emerald-50 dark:bg-emerald-900/20 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-xs text-emerald-700 dark:text-emerald-300 font-medium">طريقة الدفع</p>
                        <h4 class="font-bold">{{ $group['name'] }}</h4>
                    </div>
                    <div class="text-sm font-bold text-emerald-700 dark:text-emerald-300">
                        الإجمالي:
                        @forelse($group['totals'] as $total)
                            <span>{{ $total['formatted'] }}</span>@if(! $loop->last) · @endif
                        @empty
                            <span>0</span>
                        @endforelse
                    </div>
                </div>
                <table class="w-full text-sm text-right">
                    <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                        <th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">الاسم</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y dark:divide-slate-700">
                    @foreach($group['payments'] as $payment)
                        <tr class="{{ $payment->is_reversed ? 'opacity-50 line-through' : 'bg-emerald-50/70 dark:bg-emerald-900/20' }}">
                            <td class="px-3 py-2">
                                <a href="{{ route('cp.payments.show', $payment) }}" class="text-emerald-700 dark:text-emerald-300 font-bold">{{ $payment->currency->format($payment->amount) }}</a>
                                @if($payment->isFx())
                                    <div class="text-xs text-slate-500">{{ $payment->fxCurrency?->format($payment->source_amount) }} × {{ $payment->formattedExchangeRate() }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                {{ $payment->name }}
                                @include('cp.partials.note-line', ['notes' => $payment->notes])
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ format_date($payment->occurred_on) }}</td>
                            <td class="px-3 py-2">
                                @unless($payment->is_reversed)
                                <div class="flex items-center gap-1 justify-end">
                                    <a href="{{ route('cp.payments.edit', $payment) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                                    <form method="post" action="{{ route('cp.payments.destroy', $payment) }}" onsubmit="return confirm('حذف هذه الدفعة؟')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                                    </form>
                                </div>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-8 text-center text-slate-500">لا توجد دفعات{{ $from || $to ? ' في هذه الفترة' : '' }}.</div>
        @endforelse
    </section>

    <section class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
        <h3 class="font-bold mb-4">السجل الزمني</h3>
        <ol class="relative border-s border-slate-200 dark:border-slate-700 ms-3 space-y-4">
            @forelse($timeline as $item)
            <li class="ms-6">
                <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full {{ $item['type']==='payment' ? 'bg-emerald-500' : 'bg-primary' }}"></span>
                <p class="text-xs text-slate-500">{{ format_date($item['date']) }}</p>
                <p class="font-medium">{{ $item['title'] }} — {{ $item['currency']->format($item['amount']) }}</p>
                @include('cp.partials.note-line', ['notes' => $item['notes'] ?? null])
            </li>
            @empty
            <li class="ms-6 text-slate-500 text-sm">لا حركات{{ $from || $to ? ' في هذه الفترة' : '' }}.</li>
            @endforelse
        </ol>
    </section>
</div>
@endsection
