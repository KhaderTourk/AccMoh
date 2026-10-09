@php [$from, $to] = \App\Support\DateRange::fromRequest(); @endphp
<div>
    <label class="text-xs block mb-0.5 text-slate-500">من تاريخ</label>
    @include('cp.partials.date-field', ['name' => 'from', 'value' => $from, 'class' => 'w-full rounded-xl border px-3 py-2 dark:bg-slate-700 block text-left'])
</div>
<div>
    <label class="text-xs block mb-0.5 text-slate-500">إلى تاريخ</label>
    @include('cp.partials.date-field', ['name' => 'to', 'value' => $to, 'class' => 'w-full rounded-xl border px-3 py-2 dark:bg-slate-700 block text-left'])
</div>
