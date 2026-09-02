@extends('cp.layout')
@section('title', 'مصروف جديد')
@section('content')
<div x-data="expenseForm()" x-init="init()" class="max-w-2xl">
<form method="post" action="{{ route('cp.expenses.store') }}" class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="text-sm">الصندوق *</label>
            <select name="fund_id" x-model="fundId" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                @foreach($funds as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm">التصنيف</label>
            <select name="expense_category_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">—</option>
                <template x-for="c in filteredCategories" :key="c.id">
                    <option :value="c.id" x-text="c.name" :selected="String(c.id) === String(selectedCategoryId)"></option>
                </template>
            </select>
        </div>
        @if(tenantBusinessEnabled() && ($vendors ?? collect())->isNotEmpty())
        <div class="md:col-span-2" x-show="showVendors">
            <label class="text-sm">ابن الشركة / مورد</label>
            <select name="vendor_id" x-model="vendorId" @change="onVendorChange()" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                <option value="">—</option>
                @foreach($vendors as $v)
                    <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->type->label() }})</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="md:col-span-2">
            <label class="text-sm">الجهة *</label>
            <input name="description" x-model="description" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
        <div><label class="text-sm">المبلغ *</label><input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">العملة *</label><select name="currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></div>
        <div><label class="text-sm">طريقة الدفع *</label><select name="payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
        <div><label class="text-sm">التاريخ *</label><input type="date" name="expense_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div>
            <label class="text-sm">المستلم</label>
            <input name="payee" x-model="payee" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
        </div>
    </div>
    <div><label class="text-sm">ملاحظة</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">{{ old('notes') }}</textarea></div>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
</form>
</div>
@endsection
@push('scripts')
<script>
function expenseForm() {
    const funds = @json($funds->map(fn ($f) => ['id' => $f->id, 'slug' => $f->slug])->values());
    const categories = @json($expenseCategories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'fund_slug' => $c->fund_slug])->values());
    const vendors = @json(($vendors ?? collect())->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values());
    return {
        fundId: '{{ old('fund_id', $selectedFundId ?? $funds->first()?->id) }}',
        vendorId: '{{ old('vendor_id', $selectedVendorId ?? '') }}',
        selectedCategoryId: '{{ old('expense_category_id') }}',
        description: @json(old('description')),
        payee: @json(old('payee')),
        get fundSlug() {
            const f = funds.find(x => String(x.id) === String(this.fundId));
            return f ? f.slug : null;
        },
        get showVendors() {
            return this.fundSlug === 'business' && vendors.length > 0;
        },
        get filteredCategories() {
            const slug = this.fundSlug;
            return categories.filter(c => !c.fund_slug || c.fund_slug === slug);
        },
        onVendorChange() {
            const v = vendors.find(x => String(x.id) === String(this.vendorId));
            if (!v) return;
            if (!this.description) this.description = v.name;
            if (!this.payee) this.payee = v.name;
        },
        init() {
            if (this.vendorId) this.onVendorChange();
        }
    }
}
</script>
@endpush
