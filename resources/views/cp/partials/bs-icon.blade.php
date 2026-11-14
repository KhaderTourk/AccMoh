{{-- أيقونات «أسلوب براند»: خطوط رفيعة بنفس شكل الصورة المعتمدة (11/11/2026). --}}
@php
    $bsPaths = [
        'home' => '<path d="M3 11.5 12 4l9 7.5M5.5 9.5V20h13V9.5"/>',
        'drawers' => '<rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M16 12.5h2M3 10h18"/>',
        'work' => '<rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M8.5 7V5.5A1.5 1.5 0 0 1 10 4h4a1.5 1.5 0 0 1 1.5 1.5V7M3 12.5h18"/>',
        'person' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c.8-4 3.8-6 7.5-6s6.7 2 7.5 6"/>',
        'transfer' => '<path d="M4 8h14l-3.5-3.5M20 16H6l3.5 3.5"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'ledger' => '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11M9 8h6"/>',
        'reports' => '<path d="M4 20V10M10 20V4M16 20v-7M21 20H3"/>',
        'api' => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
        'offline' => '<path d="M7 18a4.5 4.5 0 0 1-.6-8.96A6 6 0 0 1 17.7 8.6 4.7 4.7 0 0 1 17.5 18zM4 4l16 16"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .6 3.2 2.4 3.5 5.2"/>',
        'ads' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'clients' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
        'workers' => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="11" r="2.5"/><path d="M5.5 17a3.5 3.5 0 0 1 7 0M15 10h3M15 14h3"/>',
        'suppliers' => '<path d="M2 6h11v10H2zM13 9h4l4 4v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'moon' => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/>',
        'back' => '<path d="M9 6l6 6-6 6"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'exchange' => '<circle cx="9" cy="9" r="5"/><circle cx="15" cy="15" r="5"/>',
        'chevron' => '<path d="M6 9l6 6 6-6"/>',
        'more' => '<circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/>',
    ];
@endphp
<svg class="bs-ic" width="{{ $size ?? 18 }}" height="{{ $size ?? 18 }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $bsPaths[$name] !!}</svg>
