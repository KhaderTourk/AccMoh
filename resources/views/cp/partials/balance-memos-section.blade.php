<section class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-bold text-lg">رصيد مرحّل</h3>
        <div class="flex gap-3 text-sm">
            <a href="{{ $createUrl }}" class="text-primary">إضافة</a>
            <a href="{{ $transferUrl }}" class="text-primary">ترحيل لحساب آخر</a>
        </div>
    </div>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/40"><tr>
                <th class="px-3 py-2">البيان</th>
                <th class="px-3 py-2">الاتجاه</th>
                <th class="px-3 py-2">المبلغ</th>
                <th class="px-3 py-2">التاريخ</th>
                <th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @forelse($memos as $memo)
                <tr>
                    <td class="px-3 py-2">
                        {{ $memo->statementTitle() }}
                        @include('cp.partials.note-line', ['notes' => $memo->notes])
                    </td>
                    <td class="px-3 py-2">{{ $memo->direction->label() }}</td>
                    <td class="px-3 py-2 font-semibold">{{ $memo->currency->format($memo->amount) }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ format_date($memo->memo_date) }}</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-1 justify-end">
                            <a href="{{ route('cp.balance-memos.edit', $memo) }}" class="p-1" title="تعديل"><span class="material-symbols-outlined text-base">edit</span></a>
                            <form method="post" action="{{ route('cp.balance-memos.destroy', $memo) }}" onsubmit="return confirm('{{ $memo->group_id ? 'حذف هذا الترحيل من الحسابين؟' : 'حذف هذا الرصيد المرحّل؟' }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1 text-rose-600" title="حذف"><span class="material-symbols-outlined text-base">delete</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-500">لا يوجد رصيد مرحّل.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
