@extends('cp.layout')
@section('title', 'مصروف جديد')
@section('content')
<form method="post" action="{{ route('cp.expenses.store') }}" class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    <div class="grid md:grid-cols-2 gap-3">
        <div><label class="text-sm">الصندوق *</label><select name="fund_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($funds as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach</select></div>
        <div><label class="text-sm">التصنيف</label><select name="expense_category_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"><option value="">—</option>@foreach($expenseCategories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
        <div class="md:col-span-2"><label class="text-sm">الوصف *</label><input name="description" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">المبلغ *</label><input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">العملة *</label><select name="currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></div>
        <div><label class="text-sm">طريقة الدفع *</label><select name="payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
        <div><label class="text-sm">التاريخ *</label><input type="date" name="expense_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">الجهة / المستلم</label><input name="payee" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    </div>
    <div><label class="text-sm">ملاحظة</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></textarea></div>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
</form>
@endsection
