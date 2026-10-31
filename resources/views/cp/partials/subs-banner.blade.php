@php($pendingSubs = tenantBusinessEnabled() ? \App\Http\Controllers\Cp\ClientSubscriptionController::pendingCount() : 0)
@if($pendingSubs > 0)
    <a href="{{ route('cp.subscriptions.confirm') }}" class="cp-banner">
        <span class="material-symbols-outlined">event_repeat</span>
        خدمات شهر {{ now()->month }}/{{ now()->year }} جاهزة للتأكيد ({{ $pendingSubs }})
        <b>راجع وأكّد ←</b>
    </a>
@endif
