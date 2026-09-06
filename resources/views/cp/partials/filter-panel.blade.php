@php
    $count = (int) ($count ?? 0);
    $open = (bool) ($open ?? $count > 0);
    $clearUrl = $clearUrl ?? url()->current();
@endphp
<div class="space-y-0" x-data="{ filtersOpen: {{ $open ? 'true' : 'false' }} }">
    <div class="cp-toolbar">
        <div class="cp-toolbar-start">
            <button type="button" class="cp-btn cp-btn-ghost" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen.toString()">
                <span class="material-symbols-outlined">tune</span>
                <span>تصفية</span>
                <span class="material-symbols-outlined text-base transition-transform" :class="filtersOpen ? 'rotate-180' : ''">expand_more</span>
                @if($count > 0)
                    <span class="cp-filter-badge">{{ $count }}</span>
                @endif
            </button>
            {{ $start ?? '' }}
        </div>
        <div class="cp-toolbar-end">
            {{ $actions ?? '' }}
        </div>
    </div>
    <form method="get" action="{{ $action ?? url()->current() }}" x-cloak x-show="filtersOpen" x-transition class="cp-filter-card">
        <div class="cp-filter-grid">
            {{ $slot }}
        </div>
        <div class="cp-filter-actions">
            <button type="submit" class="cp-btn cp-btn-primary">
                <span class="material-symbols-outlined">search</span>
                تطبيق
            </button>
            @if($count > 0)
                <a href="{{ $clearUrl }}" class="cp-btn cp-btn-ghost">مسح التصفية</a>
            @endif
        </div>
        @isset($footer)
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">{{ $footer }}</div>
        @endisset
    </form>
</div>
