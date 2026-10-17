<span class="material-symbols-outlined pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xl text-slate-500 dark:text-slate-300">calendar_month</span>
<input type="date"
       tabindex="-1"
       aria-label="اختيار التاريخ من التقويم"
       title="اختيار التاريخ من التقويم"
       min="{{ \App\Support\DateRange::MIN_YEAR }}-01-01"
       max="{{ \App\Support\DateRange::MAX_YEAR }}-12-31"
       data-date-picker
       class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
