{{-- كرت الحساب (14/11/2026): الاسم وتحته «متصل»، بعدين العين والقمر مع اسم لكل وحدة لما توقف عليها،
     والخروج لحاله بسطر تحت بكلمة، عشان ما ينضغط بالغلط. نفس الكرت بالقائمة الجانبية وبآخر «المزيد» عالجوال. --}}
@php($bsUser = auth()->user())
@if($bsUser)
<div class="bs-user {{ !empty($more) ? 'bs-user-more' : '' }}">
    <div class="bs-uhead">
        <span class="bs-av">{{ mb_substr($bsUser->name, 0, 1) }}</span>
        <span class="bs-who"><b>{{ $bsUser->name }}</b><small data-net><i class="bs-dot"></i><span>متصل</span></small></span>
    </div>
    <div class="bs-uacts">
        @include('cp.partials.money-eye', ['cls' => 'bs-ubtn'])
        <button type="button" class="bs-ubtn" data-theme-toggle aria-label="تبديل الوضع الليلي" data-tip="الوضع الليلي">@include('cp.partials.bs-icon', ['name' => 'moon'])</button>
    </div>
    <form action="{{ route('cp.logout') }}" method="post" class="bs-out">
        @csrf
        <button type="submit">@include('cp.partials.bs-icon', ['name' => 'logout', 'size' => 16])تسجيل الخروج</button>
    </form>
</div>
@endif
