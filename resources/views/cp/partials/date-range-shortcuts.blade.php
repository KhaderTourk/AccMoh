@php
    $btn = 'inline-flex items-center px-3 py-1.5 rounded-xl border text-xs font-medium';
    $active = 'bg-primary text-white border-primary';
    $idle = 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300';
@endphp
<div class="flex flex-wrap gap-2">
    @foreach(date_range_presets() as $preset)
        <a href="{{ date_range_url($preset['from'], $preset['to']) }}"
           class="{{ $btn }} {{ date_range_preset_active($preset['from'], $preset['to']) ? $active : $idle }}">
            {{ $preset['label'] }}
        </a>
    @endforeach
</div>
