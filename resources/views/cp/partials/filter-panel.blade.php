@php
    $count = (int) ($count ?? 0);
    $modal = (bool) ($modal ?? false);
    $open = $modal ? false : (bool) ($open ?? $count > 0);
    $clearUrl = $clearUrl ?? url()->current();
@endphp
<div class="space-y-0" x-data="{ filtersOpen: {{ $open ? 'true' : 'false' }} }" @if($modal) @keydown.escape.window="filtersOpen = false" @endif>
    <div class="cp-toolbar">
        <div class="cp-toolbar-start">
            <button type="button" class="cp-btn cp-btn-ghost" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen.toString()">
                <span class="material-symbols-outlined">tune</span>
                <span>تصفية</span>
                @unless($modal)
                <span class="material-symbols-outlined text-base transition-transform" :class="filtersOpen ? 'rotate-180' : ''">expand_more</span>
                @endunless
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
    @if($modal)
    <template x-teleport="body">
        <div class="cp-filter-modal" x-cloak x-show="filtersOpen"
             x-transition:enter="cp-fm-enter" x-transition:enter-start="cp-fm-from" x-transition:enter-end="cp-fm-to"
             x-transition:leave="cp-fm-leave" x-transition:leave-start="cp-fm-to" x-transition:leave-end="cp-fm-from"
             @click.self="filtersOpen = false" role="dialog" aria-modal="true" aria-label="تصفية">
            <form method="get" action="{{ $action ?? url()->current() }}" class="cp-filter-modal-panel cp-main"
                  x-effect="if (filtersOpen) $nextTick(() => { const f = $el.querySelector('input:not([type=hidden]), select'); f && f.focus(); })">
                <div class="cp-filter-modal-head">
                    <span class="cp-filter-modal-icon"><span class="material-symbols-outlined">tune</span></span>
                    <strong>تصفية النتائج</strong>
                    <button type="button" class="cp-filter-modal-close" @click="filtersOpen = false" aria-label="إغلاق">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="cp-filter-modal-body">
                    <div class="cp-filter-grid">
                        {{ $slot }}
                    </div>
                    @isset($footer)
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700">{{ $footer }}</div>
                    @endisset
                </div>
                <div class="cp-filter-modal-foot">
                    <button type="submit" class="cp-btn cp-btn-primary">
                        <span class="material-symbols-outlined">search</span>
                        تطبيق
                    </button>
                    @if($count > 0)
                        <a href="{{ $clearUrl }}" class="cp-btn cp-btn-ghost">مسح التصفية</a>
                    @endif
                    <button type="button" class="cp-btn cp-btn-muted" @click="filtersOpen = false">إلغاء</button>
                </div>
            </form>
        </div>
    </template>
    @else
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
    @endif
</div>
