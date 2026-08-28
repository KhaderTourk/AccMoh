@php
    $btn = 'inline-flex items-center px-3 py-1.5 rounded-xl border text-xs font-medium';
    $active = 'bg-primary text-white border-primary';
    $idle = 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300';
@endphp
<div class="flex flex-wrap gap-2 w-full">
    @foreach(date_range_presets() as $key => $preset)
        <button type="submit" name="_preset" value="{{ $key }}"
           class="{{ $btn }} {{ date_range_preset_active($preset['from'], $preset['to'], $key) ? $active : $idle }}">
            {{ $preset['label'] }}
        </button>
    @endforeach
</div>
