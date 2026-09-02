@props(['notes'])
@if(filled($notes))
    <div {{ $attributes->merge(['class' => 'text-xs text-slate-500 mt-0.5 whitespace-pre-line']) }}>{{ $notes }}</div>
@endif
