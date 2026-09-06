@php
    /** @var \Illuminate\Support\Collection $summary */
    $heading = $heading ?? 'التفاصيل';
    $nameLabel = $nameLabel ?? 'الاسم';
    $billedLabel = $billedLabel ?? 'المستحقات';
    $empty = $empty ?? 'لا بيانات.';
    $routePrefix = $routePrefix ?? null;
@endphp
<section>
    <h2 class="font-bold text-lg mb-3">{{ $heading }}</h2>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-3 py-2">{{ $nameLabel }}</th>
                    <th class="px-3 py-2">العملة</th>
                    <th class="px-3 py-2">{{ $billedLabel }}</th>
                    <th class="px-3 py-2">المدفوع</th>
                    <th class="px-3 py-2">المتبقي</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($summary as $row)
                @foreach($row['rows'] as $r)
                <tr>
                    <td class="px-3 py-2">
                        @if($routePrefix)
                            <a href="{{ route('cp.'.$routePrefix.'.show', $row['vendor']) }}" class="text-primary font-medium">{{ $row['vendor']->name }}</a>
                        @else
                            {{ $row['vendor']->name }}
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $r['currency']->name }}</td>
                    <td class="px-3 py-2">{{ $r['currency']->format($r['billed']) }}</td>
                    <td class="px-3 py-2 text-emerald-600">{{ $r['currency']->format($r['paid']) }}</td>
                    <td class="px-3 py-2 font-bold {{ \App\Support\Money::isNegative($r['due']) ? 'text-emerald-600' : (\App\Support\Money::isPositive($r['due']) ? 'text-amber-600' : '') }}">
                        @if(\App\Support\Money::isNegative($r['due']))
                            مقدماً {{ $r['currency']->format(\App\Support\Money::abs($r['due'])) }}
                        @else
                            {{ $r['currency']->format($r['due']) }}
                        @endif
                    </td>
                </tr>
                @endforeach
            @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-500">{{ $empty }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
