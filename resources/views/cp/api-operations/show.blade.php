@extends('cp.layout')
@section('title', 'عملية API #'.$operation->id)
@section('content')
<?php
    $presenter = app(\App\Support\ApiChangePresenter::class)->prime([$operation]);
    $kind = $presenter->operationKind($operation);
    $all = collect($operation->changes ?? []);
    $main = $all->reject(fn ($c) => $presenter->isInternal($c))->values();
    $internal = $all->filter(fn ($c) => $presenter->isInternal($c))->values();
    if ($main->isEmpty()) {
        [$main, $internal] = [$internal, collect()];
    }
    $counts = $operation->changeCounts();
    $tones = [
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    ];
    $canUndo = ! $operation->isUndone() && $conflicts === [];
?>
<div class="space-y-5">
    <a href="{{ route('cp.api-operations.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary">
        <span class="material-symbols-outlined text-base">arrow_forward</span> سجل عمليات الـ API
    </a>

    {{-- ملخص العملية --}}
    <div class="cp-panel rounded-2xl p-5">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <span class="shrink-0 w-14 h-14 rounded-2xl grid place-items-center {{ $operation->isUndone() ? 'bg-slate-100 text-slate-400 dark:bg-slate-700' : $tones[$kind['tone']] }}">
                <span class="material-symbols-outlined text-3xl">{{ $operation->isUndone() ? 'undo' : $kind['icon'] }}</span>
            </span>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-extrabold">{{ $operation->label() }}</h1>
                    @if($operation->isUndone())
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 px-2.5 py-0.5 text-xs font-bold">تم التراجع عنها</span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 px-2.5 py-0.5 text-xs font-bold"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> قائمة</span>
                    @endif
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">
                    نفّذها <strong>{{ $operation->user?->name ?? 'مستخدم غير معروف' }}</strong>
                    @if($operation->token_name) من <strong>«{{ $operation->token_name }}»</strong>@endif
                    في {{ format_date($operation->created_at, true) }}،
                    وأثّرت على {{ $all->count() }} {{ $all->count() === 1 ? 'سجل' : 'سجلات' }}.
                </p>
            </div>
            @unless($operation->isUndone())
                <form method="post" action="{{ route('cp.api-operations.undo', $operation) }}" onsubmit="return confirm('التراجع عن هذه العملية وإرجاع البيانات كما كانت قبلها؟')" class="shrink-0">
                    @csrf
                    <button class="cp-btn cp-btn-solid-danger disabled:opacity-50 disabled:cursor-not-allowed" @disabled($conflicts !== [])>
                        <span class="material-symbols-outlined">undo</span> تراجع عن العملية
                    </button>
                </form>
            @endunless
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-5">
            <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 p-3">
                <div class="text-xs text-slate-500 flex items-center gap-1"><span class="material-symbols-outlined text-sm">person</span> المستخدم</div>
                <div class="font-bold mt-1 truncate">{{ $operation->user?->name ?? '—' }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 p-3">
                <div class="text-xs text-slate-500 flex items-center gap-1"><span class="material-symbols-outlined text-sm">smartphone</span> التطبيق / الجهاز</div>
                <div class="font-bold mt-1 truncate">{{ $operation->token_name ?: '—' }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 p-3">
                <div class="text-xs text-slate-500 flex items-center gap-1"><span class="material-symbols-outlined text-sm">schedule</span> الوقت</div>
                <div class="font-bold mt-1">{{ format_date($operation->created_at, true) }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 p-3">
                <div class="text-xs text-slate-500 flex items-center gap-1"><span class="material-symbols-outlined text-sm">dataset</span> السجلات المتأثرة</div>
                <div class="font-bold mt-1 flex flex-wrap gap-x-2">
                    @if($counts['created'])<span class="text-emerald-600 dark:text-emerald-400">{{ $counts['created'] }} جديد</span>@endif
                    @if($counts['updated'])<span class="text-amber-600 dark:text-amber-400">{{ $counts['updated'] }} معدّل</span>@endif
                    @if($counts['deleted'])<span class="text-rose-600 dark:text-rose-400">{{ $counts['deleted'] }} محذوف</span>@endif
                </div>
            </div>
        </div>
    </div>

    {{-- الحالة وما يحدث عند التراجع --}}
    @if($operation->isUndone())
        <div class="cp-flash cp-flash-success">تم التراجع عن هذه العملية {{ format_date($operation->undone_at, true) }}@if($operation->undoer) بواسطة {{ $operation->undoer->name }}@endif، وعادت البيانات كما كانت قبلها.</div>
    @elseif($conflicts !== [])
        <div class="cp-flash cp-flash-error">
            <div class="font-semibold mb-1">لا يمكن التراجع الآن:</div>
            <ul class="list-disc pr-5">@foreach($conflicts as $c)<li>{{ $c }}</li>@endforeach</ul>
            <div class="mt-1 text-xs">تراجع عن العمليات الأحدث التي غيّرت هذه السجلات أولاً.</div>
        </div>
    @endif

    @if($canUndo)
        <div class="rounded-2xl border border-sky-200 dark:border-sky-900 bg-sky-50/70 dark:bg-sky-900/20 p-4">
            <div class="flex items-center gap-2 font-bold text-sky-800 dark:text-sky-300 mb-2">
                <span class="material-symbols-outlined">help</span> ماذا يحدث عند التراجع؟
            </div>
            <ul class="space-y-1 text-sm text-slate-700 dark:text-slate-300 pr-1">
                @foreach($main as $change)
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-base text-sky-600">chevron_left</span>
                        <span>{{ $presenter->tableLabel($change['table']) }} «{{ $presenter->recordTitle($change) }}»: {{ $presenter->opInfo($change['op'])['undo'] }}.</span>
                    </li>
                @endforeach
                @if($internal->isNotEmpty())
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-base text-sky-600">chevron_left</span>
                        <span>وتُلغى معها السجلات الداخلية المرتبطة ({{ $internal->count() }}) مثل القيود المالية التلقائية.</span>
                    </li>
                @endif
            </ul>
        </div>
    @endif

    {{-- التغييرات --}}
    <section class="space-y-3">
        <h2 class="font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">difference</span> ما الذي تغيّر؟</h2>
        @foreach($main as $change)
            @include('cp.api-operations._change', ['change' => $change, 'presenter' => $presenter])
        @endforeach
    </section>

    @if($internal->isNotEmpty())
        <details class="rounded-2xl border bg-white dark:bg-slate-800 p-4">
            <summary class="cursor-pointer text-sm font-medium text-slate-600 dark:text-slate-300 select-none">
                سجلات داخلية أنشأها النظام تلقائياً ({{ $internal->count() }})
                <span class="text-xs text-slate-400 font-normal">— مثل القيود المالية، لا تحتاج عادةً لمراجعتها</span>
            </summary>
            <div class="space-y-3 mt-3">
                @foreach($internal as $change)
                    @include('cp.api-operations._change', ['change' => $change, 'presenter' => $presenter])
                @endforeach
            </div>
        </details>
    @endif

    <details class="rounded-2xl border bg-white dark:bg-slate-800 p-4">
        <summary class="cursor-pointer text-sm font-medium text-slate-600 dark:text-slate-300 select-none">تفاصيل تقنية</summary>
        <dl class="mt-3 grid grid-cols-[auto,1fr] gap-x-4 gap-y-1 text-xs">
            <dt class="text-slate-500">رقم العملية</dt><dd class="font-mono">#{{ $operation->id }}</dd>
            <dt class="text-slate-500">الطلب</dt><dd class="font-mono" dir="ltr">{{ $operation->method }} {{ $operation->path }}</dd>
            @if($operation->route_name)<dt class="text-slate-500">المسار</dt><dd class="font-mono" dir="ltr">{{ $operation->route_name }}</dd>@endif
            @if($operation->status_code)<dt class="text-slate-500">رمز الاستجابة</dt><dd class="font-mono">{{ $operation->status_code }}</dd>@endif
            @if($operation->ip_address)<dt class="text-slate-500">عنوان IP</dt><dd class="font-mono" dir="ltr">{{ $operation->ip_address }}</dd>@endif
        </dl>
        @if($operation->request)
            <div class="text-xs text-slate-500 mt-3 mb-1">البيانات المرسلة في الطلب</div>
            <pre class="text-xs overflow-x-auto rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3" dir="ltr">{{ json_encode($operation->request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </details>
</div>
@endsection
