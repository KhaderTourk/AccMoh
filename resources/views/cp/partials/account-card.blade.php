{{-- كرت الحساب (معتمد 15/11/2026): ثابت بآخر القائمة الجانبية. الصورة والاسم بيفتحوا الإعدادات،
     تحت الاسم: العين، الهلال، ترس الإعدادات، والغيمة (بتطلع بس لما ينقطع النت أو في حركات مستنية، وبتفتح وضع عدم الاتصال).
     زر الخروج مربع لحاله على الطرف. نفس الكرت بآخر «المزيد» عالجوال، بدون الترس لأن الإعدادات موجودة بالقائمة هناك. --}}
@php($bsUser = auth()->user())
@if($bsUser)
@php($bsSettingsUrl = tenantBusinessEnabled() && cpCan('finance') ? route('cp.settings.index') : null)
<div class="bs-user {{ !empty($more) ? 'bs-user-more' : '' }}">
    @if($bsSettingsUrl && empty($more))
    <a href="{{ $bsSettingsUrl }}" class="bs-av" title="الإعدادات">{{ mb_substr($bsUser->name, 0, 1) }}</a>
    @else
    <span class="bs-av">{{ mb_substr($bsUser->name, 0, 1) }}</span>
    @endif
    <div class="bs-umeta">
        @if($bsSettingsUrl && empty($more))
        <a href="{{ $bsSettingsUrl }}" class="bs-uname">{{ $bsUser->name }}</a>
        @else
        <b class="bs-uname">{{ $bsUser->name }}</b>
        @endif
        <div class="bs-uacts">
            @include('cp.partials.money-eye', ['cls' => 'bs-ubtn'])
            <button type="button" class="bs-ubtn" data-theme-toggle aria-label="تبديل الوضع الليلي" data-tip="الوضع الليلي">@include('cp.partials.bs-icon', ['name' => 'moon'])</button>
            @if($bsSettingsUrl && empty($more))
            <a href="{{ $bsSettingsUrl }}" class="bs-ubtn {{ request()->routeIs('cp.settings.*', 'cp.users.*', 'cp.style') ? 'on' : '' }}" aria-label="الإعدادات" data-tip="الإعدادات">@include('cp.partials.bs-icon', ['name' => 'gear'])</a>
            @endif
            <a href="{{ route('cp.offline') }}" class="bs-ubtn bs-net" data-net-btn data-tip="متصل" aria-label="وضع عدم الاتصال">@include('cp.partials.bs-icon', ['name' => 'offline'])<i class="bs-net-n" data-offline-count></i></a>
        </div>
    </div>
    <form action="{{ route('cp.logout') }}" method="post" class="bs-out">
        @csrf
        <button type="submit" class="bs-outbtn" aria-label="تسجيل الخروج" data-tip="تسجيل الخروج">@include('cp.partials.bs-icon', ['name' => 'logout'])</button>
    </form>
</div>
@endif
