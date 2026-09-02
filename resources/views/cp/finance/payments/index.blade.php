@extends('cp.layout')
@section('title', 'دفعات العملاء')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between gap-3 flex-wrap">
        <form class="flex flex-wrap gap-2 items-end">
            <select name="client_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">كل العملاء</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->name }}</option>@endforeach</select>
            <select name="currency_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">العملة</option>@foreach($currencies as $c)<option value="{{ $c->id }}" @selected(request('currency_id')==$c->id)>{{ $c->code }}</option>@endforeach</select>
            @include('cp.partials.date-range-fields')
            <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث في الملاحظات" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
            @include('cp.partials.date-range-shortcuts')
        </form>
        <a href="{{ route('cp.payments.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">دفعة جديدة</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">العميل</th><th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($payments as $p)
                <tr class="{{ $p->is_reversed ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2">
                        <a class="text-primary" href="{{ route('cp.clients.show', $p->client) }}">{{ $p->client->name }}</a>
                        @include('cp.partials.note-line', ['notes' => $p->notes])
                    </td>
                    <td class="px-3 py-2">
                        {{ $p->currency->format($p->amount) }}
                        @if($p->isFx())
                            <div class="text-xs text-slate-500">{{ $p->fxCurrency?->format($p->source_amount) }} × {{ $p->formattedExchangeRate() }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $p->paymentMethod->name }}</td>
                    <td class="px-3 py-2">{{ $p->payment_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2 flex gap-2 justify-end">
                        <a href="{{ route('cp.payments.show', $p) }}" class="text-sm text-primary">عرض</a>
                        @unless($p->is_reversed)
                        <form method="post" action="{{ route('cp.payments.reverse', $p) }}" onsubmit="return confirm('إلغاء الدفعة؟')">@csrf<button class="text-rose-600 text-xs">إلغاء</button></form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-8 text-center text-slate-500">لا توجد دفعات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $payments->links() }}</div>
    </div>
</div>
@endsection
