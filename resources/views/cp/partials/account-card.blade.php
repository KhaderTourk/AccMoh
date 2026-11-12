{{-- كرت الحساب (12/11/2026): الاسم بسطر كامل، تحته «متصل»، وتحتهم الأيقونات الثلاث: العين، القمر، الخروج.
     نفس الكرت بالقائمة الجانبية وبآخر «المزيد» عالجوال. --}}
@php($bsUser = auth()->user())
@if($bsUser)
<div class="bs-user {{ !empty($more) ? 'bs-user-more' : '' }}">
    <div class="bs-uhead">
        <span class="bs-av">{{ mb_substr($bsUser->name, 0, 1) }}</span>
        <span class="bs-who"><b>{{ $bsUser->name }}</b><small data-net><i class="bs-dot"></i><span>متصل</span></small></span>
    </div>
    <div class="bs-uacts">
        @include('cp.partials.money-eye', ['cls' => 'bs-ubtn'])
        <button type="button" class="bs-ubtn" data-theme-toggle aria-label="تبديل الوضع الليلي" title="الوضع الليلي">@include('cp.partials.bs-icon', ['name' => 'moon'])</button>
        <form action="{{ route('cp.logout') }}" method="post">
            @csrf
            <button type="submit" class="bs-ubtn" aria-label="تسجيل الخروج" title="تسجيل الخروج">@include('cp.partials.bs-icon', ['name' => 'logout'])</button>
        </form>
    </div>
</div>
@endif
