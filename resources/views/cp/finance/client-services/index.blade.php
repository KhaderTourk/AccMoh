@extends('cp.layout')
@section('title', 'الخدمات المقدمة')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between flex-wrap gap-3">
        <form class="flex flex-wrap gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث" class="rounded-xl border px-3 py-2 dark:bg-slate-700">
            <select name="client_id" class="rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">العميل</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->name }}</option>@endforeach</select>
            <button class="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-700">تصفية</button>
        </form>
        <a href="{{ route('cp.client-services.create') }}" class="px-4 py-2 rounded-xl bg-primary text-white">خدمة جديدة</a>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">العميل</th><th class="px-3 py-2">الخدمة</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2">المتبقي</th><th class="px-3 py-2">التاريخ</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($services as $s)
                <tr>
                    <td class="px-3 py-2"><a href="{{ route('cp.clients.show', $s->client) }}" class="text-primary">{{ $s->client->name }}</a></td>
                    <td class="px-3 py-2">{{ $s->title }}</td>
                    <td class="px-3 py-2">{{ $s->currency->format($s->amount) }}</td>
                    <td class="px-3 py-2">{{ $s->currency->format($s->remainingAmount()) }}</td>
                    <td class="px-3 py-2">{{ $s->service_date->format('Y-m-d') }}</td>
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
