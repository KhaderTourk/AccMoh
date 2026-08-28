<label class="flex items-center gap-2 text-sm cursor-pointer">
    <input type="hidden" name="requires_fx" value="0">
    <input type="checkbox" name="requires_fx" value="1" x-model="requiresFx" class="rounded border">
    سعر الصرف / دولار
</label>

<div x-show="!requiresFx">
    <label class="text-sm">المبلغ / شيكل *</label>
    <input type="number" step="0.01" min="0.01" name="amount" x-model="ilsAmount" x-bind:disabled="requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
</div>

<div x-show="requiresFx" x-cloak class="space-y-3 rounded-xl border border-dashed p-4">
    <div>
        <label class="text-sm">المبلغ / دولار *</label>
        <input type="number" step="0.01" min="0.01" name="source_amount" x-model="usdAmount" x-bind:disabled="!requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
    </div>
    <div>
        <label class="text-sm">سعر الدولار *</label>
        <input type="number" step="0.0001" min="0.0001" name="exchange_rate" x-model="rate" x-bind:disabled="!requiresFx" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" placeholder="مثال: 3.65">
    </div>
    <div>
        <label class="text-sm">الإجمالي / شيكل</label>
        <input type="text" :value="ilsTotal()" readonly tabindex="-1" class="w-full rounded-xl border px-3 py-2 bg-slate-50 dark:bg-slate-700/60">
        <input type="hidden" name="amount" :value="ilsTotal()" x-bind:disabled="!requiresFx">
    </div>
    <p class="text-xs text-slate-500">تُسجَّل الحركة بالشيكل. الإجمالي = المبلغ بالدولار × سعر الدولار.</p>
</div>
