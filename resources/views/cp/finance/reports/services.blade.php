@extends('cp.layout')
@section('title', 'دخل الخدمات')
@section('content')
@php
    $n = fn ($v) => abs($v) < 0.005 ? '—' : number_format($v, 2);
    $filterCount = collect(['from', 'to', '_preset'])->filter(fn ($k) => filled(request($k)))->count();
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('cp.reports.index') }}" class="cp-btn cp-btn-ghost">التقارير</a>
        <h1 class="text-xl font-extrabold">دخل الخدمات حسب المجموعة</h1>
    </div>
    @component('cp.partials.filter-panel', ['count' => $filterCount, 'open' => true])
            @include('cp.partials.date-range-fields')
            @slot('footer')
                @include('cp.partials.date-range-shortcuts')
            @endslot
    @endcomponent
    <p class="text-sm text-slate-500">بالشيكل. الخدمات اللي ما إلها شقين بيطلع إلها المجموع بس، والحركات القديمة اللي ما انحدد نوع شغلها تحت «بدون تحديد».</p>

    <div class="rounded-2xl border bg-white dark:bg-slate-800 sr-wrap">
        <table class="sr-table">
            <thead>
                <tr><th>الخدمة</th><th class="n">تصميم</th><th class="n">تنفيذ</th><th class="n">الاثنين مع بعض</th><th class="n">بدون تحديد</th><th class="n">المجموع</th></tr>
            </thead>
            <tbody>
                @forelse($sections as $sec)
                    <tr class="g"><td>{{ $sec['name'] }}</td><td class="n">{{ $n($sec['design']) }}</td><td class="n">{{ $n($sec['execution']) }}</td><td class="n">{{ $n($sec['both']) }}</td><td class="n">{{ $n($sec['none']) }}</td><td class="n">{{ $n($sec['total']) }}</td></tr>
                    @foreach($sec['rows'] as $r)
                        <tr>
                            <td>{{ $r['name'] }}@if($r['off']) <small class="text-slate-400">· موقوفة</small>@endif <small class="text-slate-400">· {{ $r['n'] }} حركة</small></td>
                            @if($r['phases'])
                                <td class="n">{{ $n($r['design']) }}</td><td class="n">{{ $n($r['execution']) }}</td><td class="n">{{ $n($r['both']) }}</td><td class="n">{{ $n($r['none']) }}</td>
                            @else
                                <td class="n">—</td><td class="n">—</td><td class="n">—</td><td class="n">—</td>
                            @endif
                            <td class="n">{{ $n($r['total']) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="6" class="text-slate-500">ما في خدمات بهالفترة.</td></tr>
                @endforelse
            </tbody>
            @if($sections->isNotEmpty())
                <tfoot>
                    <tr class="t"><td>المجموع ₪</td><td class="n">{{ $n($grand['design']) }}</td><td class="n">{{ $n($grand['execution']) }}</td><td class="n">{{ $n($grand['both']) }}</td><td class="n">{{ $n($grand['none']) }}</td><td class="n">{{ $n($grand['total']) }}</td></tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
