@extends('cp.layout')
@section('title', 'الخدمات المقدمة')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between flex-wrap gap-3">
        <form class="flex flex-wrap gap-2 items-end">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <select name="client_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">العميل</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->name }}</option>@endforeach</select>
            @include('cp.partials.date-range-fields')
            <button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
            @include('cp.partials.date-range-shortcuts')
        </form>
        <a href="{{ route('cp.client-services.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">خدمة جديدة</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">العميل</th><th class="px-3 py-2">الخدمة</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($services as $s)
                <tr>
                    <td class="px-3 py-2"><a href="{{ route('cp.clients.show', $s->client) }}" class="text-primary">{{ $s->client->name }}</a></td>
                    <td class="px-3 py-2">
                        {{ $s->title }}
                        @include('cp.partials.note-line', ['notes' => $s->notes])
                    </td>
                    <td class="px-3 py-2">
                        {{ $s->currency->format($s->amount) }}
                        @if($s->isFx())
                            <div class="text-xs text-slate-500">{{ $s->fxCurrency?->format($s->source_amount) }} × {{ $s->formattedExchangeRate() }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $s->service_date->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">
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
                <tr><td colspan="5" class="p-8 text-center text-slate-500">لا توجد خدمات.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $services->links() }}</div>
    </div>
</div>
@endsection
