@php
    $fieldName = $name;
    $fieldValue = \App\Support\DateRange::inputValue($value ?? null);
    $isRequired = (bool) ($required ?? false);
    $fieldClass = $class ?? 'w-full rounded-xl border px-3 py-2 dark:bg-slate-700 text-left';
@endphp
<div class="relative" data-date-wrap>
    <input type="text"
           name="{{ $fieldName }}"
           value="{{ $fieldValue }}"
           @if($isRequired) required @endif
           inputmode="none"
           placeholder="اختر التاريخ"
           autocomplete="off"
           dir="ltr"
           maxlength="10"
           data-date-display
           class="{{ $fieldClass }} cursor-pointer caret-transparent"
           style="padding-right: 2.75rem">
    @include('cp.partials.date-picker-button')
</div>
