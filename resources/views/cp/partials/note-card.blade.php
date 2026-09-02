@props(['notes', 'title' => 'ملاحظات'])
@if(filled($notes))
<div {{ $attributes->merge(['class' => 'rounded-2xl border bg-white dark:bg-slate-800 p-5']) }}>
    <h3 class="font-bold mb-2">{{ $title }}</h3>
    <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $notes }}</p>
</div>
@endif
