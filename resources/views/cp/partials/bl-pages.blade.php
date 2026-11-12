{{-- صفحات بستايل البراند (بدل روابط Laravel): «أحدث» / «أقدم» وبينهم رقم الصفحة. $p = paginator --}}
@if($p->hasPages())
<nav class="bl-pages" aria-label="الصفحات">
    @if($p->onFirstPage())<span class="cp-btn cp-btn-muted is-off">→ أحدث</span>@else<a class="cp-btn cp-btn-muted" href="{{ $p->withQueryString()->previousPageUrl() }}">→ أحدث</a>@endif
    <span class="bl-pnum">صفحة {{ $p->currentPage() }} من {{ $p->lastPage() }}</span>
    @if($p->hasMorePages())<a class="cp-btn cp-btn-muted" href="{{ $p->withQueryString()->nextPageUrl() }}">أقدم ←</a>@else<span class="cp-btn cp-btn-muted is-off">أقدم ←</span>@endif
</nav>
@endif
