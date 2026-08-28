@extends('cp.layout')
@section('title', $client->name)
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold">{{ $client->name }}</h2>
            <p class="text-slate-500 text-sm">
                @if($client->contact_name){{ $client->contact_name }} · @endif
                {{ $client->phone }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cp.client-services.create', ['client_id' => $client->id]) }}" class="px-3 py-2 rounded-xl bg-primary text-white text-sm">خدمة</a>
            <a href="{{ route('cp.payments.create', ['client_id' => $client->id]) }}" class="px-3 py-2 rounded-xl border text-sm">دفعة</a>
            <a href="{{ route('cp.clients.export-pdf', $client) }}" class="px-3 py-2 rounded-xl border text-sm">تصدير PDF</a>
            <a href="{{ route('cp.clients.edit', $client) }}" class="px-3 py-2 rounded-xl border text-sm">تعديل</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($currencies as $currency)
            @php
                $billed = $client->billedAmount($currency->id);
                $paid = $client->paidAmount($currency->id);
                $due = $client->outstandingAmount($currency->id);
            @endphp
            @if(!\App\Support\Money::isZero($billed) || !\App\Support\Money::isZero($paid))
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
                <h3 class="font-bold mb-3">{{ $currency->name }}</h3>
                <div class="space-y-1 text-sm">
                    <p>قيمة الخدمات: <strong>{{ $currency->format($billed) }}</strong></p>
                    <p>المدفوع: <strong class="text-emerald-600">{{ $currency->format($paid) }}</strong></p>
                    @if(\App\Support\Money::isNegative($due))
                        <p>عربون / رصيد مدفوع مقدماً: <strong class="text-emerald-600">{{ $currency->format(\App\Support\Money::abs($due)) }}</strong></p>
                    @else
                        <p>المتبقي: <strong class="text-amber-600">{{ $currency->format($due) }}</strong></p>
                    @endif
                </div>
            </div>
            @endif
        @endforeach
    </div>

    <section class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <div class="px-4 py-3 border-b font-bold">الخدمات</div>
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                <th class="px-3 py-2">الخدمة</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($client->services as $service)
                <tr>
                    <td class="px-3 py-2">{{ $service->title }}</td>
                    <td class="px-3 py-2">
                        {{ $service->currency->format($service->amount) }}
                        @if($service->isFx())
                            <div class="text-xs text-slate-500">{{ $service->fxCurrency?->format($service->source_amount) }} × {{ $service->formattedExchangeRate() }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $service->service_date->format('Y-m-d') }}</td>
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
            @empty
                <tr><td colspan="4" class="p-6 text-center text-slate-500">لا توجد خدمات.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <div class="px-4 py-3 border-b font-bold">الدفعات</div>
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                <th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">المرسل</th><th class="px-3 py-2">التاريخ</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($client->payments as $payment)
                <tr class="{{ $payment->is_reversed ? 'opacity-50 line-through' : '' }}">
                    <td class="px-3 py-2">
                        <a href="{{ route('cp.payments.show', $payment) }}" class="text-primary">{{ $payment->currency->format($payment->amount) }}</a>
                        @if($payment->isFx())
                            <div class="text-xs text-slate-500">{{ $payment->fxCurrency?->format($payment->source_amount) }} × {{ $payment->formattedExchangeRate() }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $payment->paymentMethod->name }}</td>
                    <td class="px-3 py-2">{{ $payment->payer_name }}</td>
                    <td class="px-3 py-2">{{ $payment->payment_date->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-6 text-center text-slate-500">لا توجد دفعات.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
        <h3 class="font-bold mb-4">السجل الزمني</h3>
        <ol class="relative border-s border-slate-200 dark:border-slate-700 ms-3 space-y-4">
            @foreach($timeline as $item)
            <li class="ms-6">
                <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full {{ $item['type']==='payment' ? 'bg-emerald-500' : 'bg-primary' }}"></span>
                <p class="text-xs text-slate-500">{{ $item['date']->format('Y-m-d') }}</p>
                <p class="font-medium">{{ $item['title'] }} — {{ $item['currency']->format($item['amount']) }}</p>
            </li>
            @endforeach
        </ol>
    </section>
</div>
@endsection
