@extends('cp.layout')
@section('title', 'خدمات الزبائن')
@section('content')
@php
    $filterCount = collect(['q', 'client_id', 'from', 'to', '_preset'])->filter(fn ($key) => filled(request($key)))->count();
@endphp
<div class="space-y-4">
    @component('cp.partials.filter-panel', ['count' => $filterCount])
        @slot('actions')
            <a href="{{ route('cp.client-services.create') }}" class="cp-btn cp-btn-primary">
                <span class="material-symbols-outlined">add</span>
                خدمة جديدة
            </a>
        @endslot
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">بحث</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="تفاصيل الخدمة أو الملاحظات" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">الزبون</label>
            <select name="client_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">الكل</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @include('cp.partials.date-range-fields')
        @slot('footer')
            @include('cp.partials.date-range-shortcuts')
        @endslot
    @endcomponent

    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-3">العميل</th>
                <th class="px-3 py-3">النوع</th>
                <th class="px-3 py-3">تفاصيل الخدمة</th>
                <th class="px-3 py-3">السعر</th>
                <th class="px-3 py-3">التاريخ</th>
                <th class="px-3 py-3"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($services as $s)
                <tr>
                    <td class="px-3 py-3"><a href="{{ route('cp.clients.show', $s->client) }}" class="text-primary font-medium">{{ $s->client->name }}</a></td>
                    <td class="px-3 py-3">{{ $s->serviceType?->name ?: '—' }}</td>
                    <td class="px-3 py-3">
                        {{ $s->title }}
                        @include('cp.partials.note-line', ['notes' => $s->notes])
                    </td>
                    <td class="px-3 py-3">
                        {{ $s->currency->format($s->amount) }}
                        @if($s->isFx())
                            <div class="text-xs text-slate-500">{{ $s->fxCurrency?->format($s->source_amount) }} × {{ $s->formattedExchangeRate() }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-3 whitespace-nowrap">{{ $s->service_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('cp.client-services.edit', $s) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                            <form method="post" action="{{ route('cp.client-services.destroy', $s) }}" onsubmit="return confirm('حذف هذه الخدمة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="cp-empty">لا توجد خدمات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $services->links() }}</div>
    </div>
</div>
@endsection
