{{-- One record touched by an API operation: what it is, what changed, and what undo does to it. --}}
<?php
    $info = $presenter->opInfo($change['op']);
    $fields = $presenter->fields($change);
    $plain = array_values(array_filter($fields, fn ($f) => ! $f['technical']));
    $technical = array_values(array_filter($fields, fn ($f) => $f['technical']));
    $amount = $change['op'] === 'pivot' ? null : $presenter->amount($change);
    $badge = [
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    ][$info['tone']];
    $edge = ['emerald' => 'border-r-emerald-500', 'amber' => 'border-r-amber-500', 'rose' => 'border-r-rose-500'][$info['tone']];
    $isUpdate = in_array($change['op'], ['updated'], true);
?>
<div class="rounded-2xl border border-r-4 {{ $edge }} bg-white dark:bg-slate-800 overflow-hidden">
    <div class="flex flex-wrap items-center gap-2 px-4 py-3 bg-slate-50/80 dark:bg-slate-700/30">
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold {{ $badge }}">
            <span class="material-symbols-outlined text-sm">{{ $info['icon'] }}</span>{{ $info['label'] }}
        </span>
        <span class="text-sm text-slate-500">{{ $presenter->tableLabel($change['table']) }}</span>
        <strong class="text-sm">{{ $presenter->recordTitle($change) }}</strong>
        @if($amount)<span class="text-sm font-bold" dir="ltr">{{ $amount }}</span>@endif
        @if(isset($change['key']))<span class="text-xs text-slate-400 font-mono mr-auto">#{{ $change['key'] }}</span>@endif
    </div>

    <div class="p-4 text-sm">
        @if($change['op'] === 'pivot')
            <?php $diff = $presenter->pivotDiff($change); ?>
            <div class="flex flex-wrap gap-4">
                <span class="text-emerald-700 dark:text-emerald-400">أُضيفت {{ count($diff['added']) }} صلاحية</span>
                <span class="text-rose-700 dark:text-rose-400">أُزيلت {{ count($diff['removed']) }} صلاحية</span>
                <span class="text-slate-500">العدد قبل: {{ count((array) $change['before']) }} — بعد: {{ count((array) ($change['after'] ?? [])) }}</span>
            </div>
        @elseif($plain === [] && $technical === [])
            <p class="text-slate-500">لا توجد حقول متغيّرة ظاهرة.</p>
        @elseif($isUpdate)
            @if($plain !== [])
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="text-xs text-slate-500">
                            <tr><th class="py-1.5 pl-3 font-medium w-1/4">الحقل</th><th class="py-1.5 pl-3 font-medium">القيمة السابقة</th><th class="w-6"></th><th class="py-1.5 font-medium">القيمة الجديدة</th></tr>
                        </thead>
                        <tbody class="divide-y dark:divide-slate-700">
                            @foreach($plain as $f)
                                <tr>
                                    <td class="py-2 pl-3 text-slate-500">{{ $f['label'] }}</td>
                                    <td class="py-2 pl-3"><span class="rounded-md bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300 px-1.5 py-0.5 line-through decoration-rose-300">{{ $f['before'] }}</span></td>
                                    <td class="text-slate-400"><span class="material-symbols-outlined text-base align-middle">arrow_back</span></td>
                                    <td class="py-2"><span class="rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 px-1.5 py-0.5 font-medium">{{ $f['after'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-slate-500">تغيّرت حقول تقنية فقط.</p>
            @endif
        @else
            @if($plain !== [])
                <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2">
                    @foreach($plain as $f)
                        <div class="flex gap-3 border-b border-slate-100 dark:border-slate-700/70 pb-1.5">
                            <dt class="text-slate-500 w-28 shrink-0">{{ $f['label'] }}</dt>
                            <dd class="font-medium {{ $change['op'] === 'deleted' ? 'line-through text-slate-500' : '' }}">{{ $change['op'] === 'deleted' ? $f['before'] : $f['after'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        @endif

        @if($technical !== [])
            <details class="mt-3 group">
                <summary class="cursor-pointer text-xs text-slate-400 hover:text-slate-600 select-none">حقول تقنية ({{ count($technical) }})</summary>
                <dl class="mt-2 grid grid-cols-[auto,1fr] gap-x-3 gap-y-0.5 text-xs">
                    @foreach($technical as $f)
                        <dt class="text-slate-400 font-mono" dir="ltr">{{ $f['key'] }}</dt>
                        <dd class="break-all">
                            @if($isUpdate)<span class="line-through text-slate-400">{{ $f['before'] }}</span> ← @endif
                            {{ $change['op'] === 'deleted' ? $f['before'] : $f['after'] }}
                        </dd>
                    @endforeach
                </dl>
            </details>
        @endif
    </div>
</div>
