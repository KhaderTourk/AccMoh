<div class="st-row{{ $s['active'] ? '' : ' is-off' }}">
    <div class="st-name">
        <b>{{ $s['name'] }}</b>
        <span><i class="st-bill {{ $s['billing'] }}">{{ $s['billing'] === 'monthly' ? 'شهرية' : 'لمرة وحدة' }}</i>@if($s['phases']) · <i class="st-phase">تصميم · تنفيذ</i>@endif · {{ $uses($s['uses']) }}</span>
    </div>
    {!! $switch($s) !!}
    <div class="st-act">
        <button type="button" class="cl-more" aria-label="خيارات {{ $s['name'] }}">⋯</button>
        <div class="cl-menu" hidden>
            <button type="button" data-open="service" data-url="{{ $s['update'] }}" data-id="{{ $s['id'] }}" data-phases="{{ $s['phases'] ? 1 : 0 }}" data-price="{{ $s['price'] }}" data-titled="{{ $s['titled'] }}" data-name="{{ $s['name'] }}" data-group="{{ $s['group'] }}" data-billing="{{ $s['billing'] }}" data-active="{{ $s['active'] ? 1 : 0 }}">تعديل</button>
            @if($s['uses'] === 0)
                <button type="button" class="danger" data-del="{{ $s['delete'] }}" data-what="الخدمة «{{ $s['name'] }}»">حذف</button>
            @else
                <span class="st-off">مستعملة، بتقدر تطفيها بس</span>
            @endif
        </div>
    </div>
</div>
