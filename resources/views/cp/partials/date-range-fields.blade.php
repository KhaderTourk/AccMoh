@php [$from, $to] = \App\Support\DateRange::fromRequest(); @endphp
<div>
    <label class="text-xs block mb-0.5 text-slate-500">من تاريخ</label>
    <input type="date" name="from" value="{{ $from }}" class="rounded-xl border px-3 py-2 dark:bg-slate-700 block">
</div>
<div>
    <label class="text-xs block mb-0.5 text-slate-500">إلى تاريخ</label>
    <input type="date" name="to" value="{{ $to }}" class="rounded-xl border px-3 py-2 dark:bg-slate-700 block">
</div>
