@php
    $fieldName = $name;
    $fieldValue = \App\Support\DateRange::inputValue($value ?? null);
    $isRequired = (bool) ($required ?? false);
    $fieldClass = $class ?? 'w-full rounded-xl border px-3 py-2 dark:bg-slate-700 text-left';
@endphp
<input type="text"
       name="{{ $fieldName }}"
       value="{{ $fieldValue }}"
       @if($isRequired) required @endif
       inputmode="numeric"
       placeholder="يوم\شهر\سنة"
       autocomplete="off"
       dir="ltr"
       maxlength="10"
       data-date-display
       class="{{ $fieldClass }}">
