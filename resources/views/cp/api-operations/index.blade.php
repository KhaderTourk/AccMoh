@extends('cp.layout')
@section('title', 'عمليات الـ API')
@section('content')
<?php
    $presenter = app(\App\Support\ApiChangePresenter::class)->prime($operations->getCollection());
    $status = (string) request('status', '');
    $keep = request()->except(['status', 'page']);
    $tabs = [
        '' => ['الكل', \App\Models\ApiOperation::query()->count()],
        'pending' => ['قائمة', \App\Models\ApiOperation::query()->pending()->count()],
        'undone' => ['تم التراجع عنها', \App\Models\ApiOperation::query()->whereNotNull('undone_at')->count()],
    ];
    $filterCount = (int) filled(request('user_id')) + (int) ($from || $to);
    $tones = [
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    ];
    $days = $operations->getCollection()->groupBy(fn ($op) => $op->created_at->toDateString());
    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();
?>
<div class="space-y-5">
    <div class="cp-panel rounded-2xl p-5 flex flex-col md:flex-row md:items-center gap-4">
        <span class="cp-stat-icon shrink-0"><span class="material-symbols-outlined">sync_alt</span></span>
        <div class="flex-1 min-w-0">
            <h1 class="text-lg font-bold">سجل عمليات الـ API</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                كل إضافة أو تعديل يتم من التطبيقات المتصلة بالنظام (مثل تطبيق الموبايل) يُسجَّل هنا.
                افتح أي عملية لترى ما تغيّر فيها بالضبط، ويمكنك التراجع عنها لإرجاع البيانات كما كانت قبلها.
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 {{ $tones['emerald'] }}"><span class="material-symbols-outlined text-sm">add_circle</span> إضافة</span>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 {{ $tones['amber'] }}"><span class="material-symbols-outlined text-sm">edit_note</span> تعديل</span>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 {{ $tones['rose'] }}"><span class="material-symbols-outlined text-sm">block</span> إلغاء</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach($tabs as $key => [$label, $count])
            <a href="{{ route('cp.api-operations.index', array_filter($keep + ['status' => $key], fn ($v) => $v !== '' && $v !== null)) }}"
               class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-medium transition {{ $status === $key ? 'bg-primary text-white border-primary shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:border-primary/50' }}"
               role="tab" aria-selected="{{ $status === $key ? 'true' : 'false' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-xs {{ $status === $key ? 'bg-white/20' : 'bg-slate-100 dark:bg-slate-700' }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    @component('cp.partials.filter-panel', ['count' => $filterCount, 'modal' => true, 'clearUrl' => route('cp.api-operations.index', array_filter(['status' => $status]))])
        @if($status !== '')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <div>
            <label class="text-xs block mb-0.5 text-slate-500">المستخدم</label>
            <select name="user_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">كل المستخدمين</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        @include('cp.partials.date-range-fields')
        @slot('footer')
            @include('cp.partials.date-range-shortcuts')
        @endslot
        @slot('start')
            <span class="text-sm text-slate-500">{{ $operations->total() }} عملية</span>
        @endslot
    @endcomponent

    @forelse($days as $day => $items)
        <section class="space-y-2">
            <h2 class="flex items-center gap-2 text-sm font-bold text-slate-500 dark:text-slate-400 px-1">
                <span class="material-symbols-outlined text-base">calendar_today</span>
                {{ $day === $today ? 'اليوم' : ($day === $yesterday ? 'أمس' : format_date($day)) }}
                <span class="font-normal text-xs">({{ $items->count() }})</span>
            </h2>
            <div class="rounded-2xl border bg-white dark:bg-slate-800 divide-y dark:divide-slate-700 overflow-hidden">
                @foreach($items as $op)
                    <?php
                        $kind = $presenter->operationKind($op);
                        $main = $presenter->primary($op);
                        $counts = $op->changeCounts();
                        $internal = collect($op->changes ?? [])->filter(fn ($c) => $presenter->isInternal($c))->count();
                        $title = $main ? $presenter->recordTitle($main) : null;
                        $amount = $main ? $presenter->amount($main) : null;
                    ?>
                    <article class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 transition hover:bg-slate-50 dark:hover:bg-slate-700/30 {{ $op->isUndone() ? 'bg-slate-50/70 dark:bg-slate-900/20' : '' }}">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <span class="shrink-0 w-11 h-11 rounded-2xl grid place-items-center {{ $op->isUndone() ? 'bg-slate-100 text-slate-400 dark:bg-slate-700' : $tones[$kind['tone']] }}">
                                <span class="material-symbols-outlined">{{ $op->isUndone() ? 'undo' : $kind['icon'] }}</span>
                            </span>
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('cp.api-operations.show', $op) }}" class="font-bold hover:text-primary {{ $op->isUndone() ? 'text-slate-500 line-through decoration-slate-300' : '' }}">{{ $op->label() }}</a>
                                    @if($op->isUndone())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 px-2 py-0.5 text-[11px] font-bold"><span class="material-symbols-outlined text-xs">undo</span> تم التراجع</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 px-2 py-0.5 text-[11px] font-bold"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> قائمة</span>
                                    @endif
                                </div>
                                @if($title)
                                    <div class="text-sm text-slate-700 dark:text-slate-200">
                                        {{ $title }}
                                        @if($amount)<span class="font-bold mr-1" dir="ltr">{{ $amount }}</span>@endif
                                    </div>
                                @endif
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">person</span>{{ $op->user?->name ?? '—' }}</span>
                                    @if($op->token_name)
                                        <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">smartphone</span>{{ $op->token_name }}</span>
                                    @endif
                                    <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">schedule</span><span dir="ltr">{{ $op->created_at->format('H:i') }}</span></span>
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">dataset</span>
                                        @if($counts['created'])<span class="text-emerald-600 dark:text-emerald-400">{{ $counts['created'] }} جديد</span>@endif
                                        @if($counts['updated'])<span class="text-amber-600 dark:text-amber-400">{{ $counts['updated'] }} معدّل</span>@endif
                                        @if($counts['deleted'])<span class="text-rose-600 dark:text-rose-400">{{ $counts['deleted'] }} محذوف</span>@endif
                                    </span>
                                    @if($internal)
                                        <span class="text-slate-400">(منها {{ $internal }} قيد تلقائي)</span>
                                    @endif
                                </div>
                                @if($op->isUndone())
                                    <div class="text-xs text-slate-500">تراجع عنها {{ $op->undoer?->name ?? '—' }} في {{ format_date($op->undone_at, true) }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 sm:shrink-0 mr-14 sm:mr-0">
                            <a href="{{ route('cp.api-operations.show', $op) }}" class="cp-btn cp-btn-ghost text-sm">
                                <span class="material-symbols-outlined">visibility</span> التفاصيل
                            </a>
                            @unless($op->isUndone())
                                <form method="post" action="{{ route('cp.api-operations.undo', $op) }}" onsubmit="return confirm('التراجع عن هذه العملية وإرجاع البيانات كما كانت قبلها؟')">
                                    @csrf
                                    <button class="cp-btn cp-btn-danger text-sm" title="التراجع عن العملية">
                                        <span class="material-symbols-outlined">undo</span> تراجع
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="rounded-2xl border bg-white dark:bg-slate-800">
            <div class="p-12 text-center text-slate-500">لا توجد عمليات عبر الـ API{{ $filterCount || $status !== '' ? ' تطابق التصفية' : '' }}.</div>
        </div>
    @endforelse

    @if($operations->hasPages())
        <div>{{ $operations->links() }}</div>
    @endif
</div>
@endsection
