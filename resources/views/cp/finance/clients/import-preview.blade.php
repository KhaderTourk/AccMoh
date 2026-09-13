@extends('cp.layout')
@section('title', 'معاينة الاستيراد')
@section('content')
@php
    $ready = collect($drafts)->filter(fn ($d) => empty($d['errors']))->count();
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">معاينة الاستيراد</h2>
            <p class="text-sm text-slate-500 mt-1">راجع البيانات ثم أكّد. لم يُحفظ شيء في النظام بعد.</p>
        </div>
        <a href="{{ route('cp.clients.import') }}" class="px-4 py-2 rounded-xl border">رفع ملفات أخرى</a>
    </div>

    <form method="post" action="{{ route('cp.clients.import.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        @foreach($drafts as $i => $draft)
            <section class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
                <div class="px-5 py-4 border-b dark:border-slate-700 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-2 min-w-[16rem] flex-1">
                        <p class="text-xs text-slate-500">{{ $draft['filename'] }}</p>
                        <label class="text-sm block">اسم العميل
                            <input name="clients[{{ $i }}][client_name]" value="{{ old("clients.$i.client_name", $draft['client_name']) }}" class="mt-1 w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                        </label>
                        @if($draft['existing_client_id'])
                            <label class="inline-flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300">
                                <input type="hidden" name="clients[{{ $i }}][merge]" value="0">
                                <input type="checkbox" name="clients[{{ $i }}][merge]" value="1" @checked(old("clients.$i.merge", true))>
                                دمج مع العميل الحالي «{{ $draft['existing_client_name'] }}»
                            </label>
                        @endif
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="clients[{{ $i }}][skip]" value="1">
                        تخطي هذا الملف
                    </label>
                </div>

                <div class="px-5 py-4 space-y-3 text-sm">
                    @if(!empty($draft['errors']))
                        <div class="rounded-xl bg-red-500/10 text-red-600 px-3 py-2">{{ implode(' ', $draft['errors']) }}</div>
                    @endif
                    @foreach($draft['warnings'] as $warning)
                        <div class="rounded-xl bg-amber-500/10 text-amber-800 dark:text-amber-200 px-3 py-2">{{ $warning }}</div>
                    @endforeach

                    <p class="text-slate-500">
                        {{ count($draft['services']) }} خدمة · {{ count($draft['payments']) }} دفعة
                        @if($draft['phone']) · هاتف: {{ $draft['phone'] }}@endif
                        @if($draft['company_name']) · جهة: {{ $draft['company_name'] }}@endif
                    </p>

                    <details open class="rounded-xl border dark:border-slate-700">
                        <summary class="cursor-pointer px-3 py-2 font-medium">الخدمات</summary>
                        <div class="overflow-x-auto">
                            <table class="w-full text-right">
                                <thead class="bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500">
                                    <tr><th class="px-3 py-2">الخدمة</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2">المبلغ</th></tr>
                                </thead>
                                <tbody class="divide-y dark:divide-slate-700">
                                @forelse($draft['services'] as $service)
                                    <tr>
                                        <td class="px-3 py-2">{{ $service['title'] }}</td>
                                        <td class="px-3 py-2">{{ $service['service_date'] }}</td>
                                        <td class="px-3 py-2">{{ $service['amount'] }} {{ $service['currency_code'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-3 py-4 text-slate-500">لا خدمات.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <details class="rounded-xl border dark:border-slate-700">
                        <summary class="cursor-pointer px-3 py-2 font-medium">الدفعات</summary>
                        <div class="overflow-x-auto">
                            <table class="w-full text-right">
                                <thead class="bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500">
                                    <tr><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2">المبلغ</th><th class="px-3 py-2">ملاحظات</th></tr>
                                </thead>
                                <tbody class="divide-y dark:divide-slate-700">
                                @forelse($draft['payments'] as $payment)
                                    <tr>
                                        <td class="px-3 py-2">{{ $payment['payment_date'] }}</td>
                                        <td class="px-3 py-2">{{ $payment['amount'] }} {{ $payment['currency_code'] }}</td>
                                        <td class="px-3 py-2">{{ $payment['notes'] ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-3 py-4 text-slate-500">لا دفعات.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </section>
        @endforeach

        <div class="flex flex-wrap gap-2">
            <button class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-primary text-white" @disabled($ready === 0)>
                <span class="material-symbols-outlined">done_all</span>
                تأكيد الاستيراد
            </button>
            <a href="{{ route('cp.clients.index') }}" class="px-5 py-2 rounded-xl border">إلغاء</a>
        </div>
    </form>
</div>
@endsection
