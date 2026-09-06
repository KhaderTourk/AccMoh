@extends('cp.layout')
@section('title', $member->name)
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold">{{ $member->name }}</h2>
            <p class="text-slate-500 text-sm">{{ $member->relationship }} {{ $member->phone }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('cp.payments.create', ['incoming', 'person_id' => $member->id]) }}" class="cp-btn cp-btn-in"><span class="material-symbols-outlined">south_west</span> دفعة واردة</a>
            <a href="{{ route('cp.payments.create', ['outgoing', 'person_id' => $member->id]) }}" class="cp-btn cp-btn-out"><span class="material-symbols-outlined">north_east</span> دفعة صادرة</a>
            <a href="{{ route('cp.persons.export-pdf', $member) }}" class="cp-btn cp-btn-ghost"><span class="material-symbols-outlined">picture_as_pdf</span> تصدير PDF</a>
            <a href="{{ route('cp.persons.edit', $member) }}" class="cp-btn cp-btn-ghost"><span class="material-symbols-outlined">edit</span> تعديل</a>
        </div>
    </div>
    @include('cp.partials.note-card', ['notes' => $member->notes])

    <div class="grid md:grid-cols-3 gap-4">
        @foreach($currencies as $c)
            @php
                $in = $member->incomingAmount($c->id);
                $out = $member->outgoingAmount($c->id);
                $net = $member->netAmount($c->id);
            @endphp
            @if(!\App\Support\Money::isZero($in) || !\App\Support\Money::isZero($out))
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
                <h3 class="font-bold mb-2">{{ $c->name }}</h3>
                <p class="text-sm">وارد: <strong class="text-emerald-600">{{ $c->format($in) }}</strong></p>
                <p class="text-sm">صادر: <strong class="text-rose-600">{{ $c->format($out) }}</strong></p>
                <p class="text-sm mt-2">الصافي: <strong class="{{ \App\Support\Money::isNegative($net) ? 'text-rose-600' : 'text-emerald-600' }}">{{ $c->format($net) }}</strong></p>
            </div>
            @endif
        @endforeach
    </div>

    <section class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <div class="px-4 py-3 border-b font-bold">الدفعات</div>
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                <th class="px-3 py-2">التاريخ</th><th class="px-3 py-2">النوع</th><th class="px-3 py-2">الدرج</th><th class="px-3 py-2">الطريقة</th><th class="px-3 py-2">المبلغ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($member->cashPayments as $p)
                <tr class="{{ $p->is_reversed ? 'opacity-40' : $p->direction->rowClass() }}">
                    <td class="px-3 py-2 whitespace-nowrap">{{ $p->occurred_on->format('Y-m-d') }}</td>
                    <td class="px-3 py-2 {{ $p->direction->colorClass() }}">{{ $p->direction->label() }}</td>
                    <td class="px-3 py-2">{{ $p->fund->name }}</td>
                    <td class="px-3 py-2">{{ $p->paymentMethod->name }}</td>
                    <td class="px-3 py-2 font-bold {{ $p->direction->colorClass() }}">
                        {{ $p->currency->format($p->amount) }}
                        @if($p->isFx())
                            <div class="text-xs font-normal text-slate-500">{{ $p->fxCurrency?->format($p->source_amount) }} × {{ $p->formattedExchangeRate() }}</div>
                        @endif
                        @include('cp.partials.note-line', ['notes' => $p->notes])
                    </td>
                    <td class="px-3 py-2">
                        @unless($p->is_reversed)
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('cp.payments.edit', $p) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                            <form method="post" action="{{ route('cp.payments.destroy', $p) }}" onsubmit="return confirm('حذف هذه الدفعة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                            </form>
                        </div>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-8 text-center text-slate-500">لا توجد دفعات.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
