@php($subsDue = tenantBusinessEnabled() ? \App\Http\Controllers\Cp\ClientSubscriptionController::pendingSummary() : ['count' => 0])
@if($subsDue['count'] > 0)
    <a href="{{ route('cp.subscriptions.confirm') }}" class="cp-banner">
        <span class="material-symbols-outlined">event_repeat</span>
        <span>فواتير الاشتراكات جاهزة:
            @if($subsDue['closed']){{ $subsDue['closed'] }} عن أشهر انتهت (حسب أيام الخدمة)@endif
            @if($subsDue['closed'] && $subsDue['fresh']) و@endif
            @if($subsDue['fresh']){{ $subsDue['fresh'] }} لشهر {{ now()->month }}/{{ now()->year }} الجديد@endif
        </span>
        <b>راجع وسجّل ←</b>
    </a>
@endif
