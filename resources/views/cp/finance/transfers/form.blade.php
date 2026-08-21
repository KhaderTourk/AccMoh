@extends('cp.layout')
@section('title', 'تحويل بين طرق الدفع')
@section('content')
<form method="post" action="{{ route('cp.transfers.store') }}" class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    <p class="text-sm text-slate-500">التحويل ينقل المال داخل نفس الصندوق بين طريقتي دفع. الإجمالي يبقى ثابتاً (ما عدا الرسوم إن وُجدت).</p>
    <div class="grid md:grid-cols-2 gap-3">
        <div><label class="text-sm">الصندوق *</label><select name="fund_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($funds as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach</select></div>
        <div><label class="text-sm">العملة *</label><select name="currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></div>
        <div><label class="text-sm">من *</label><select name="from_payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
        <div><label class="text-sm">إلى *</label><select name="to_payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
        <div><label class="text-sm">المبلغ *</label><input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">رسوم (اختياري)</label><input type="number" step="0.01" min="0" name="fee_amount" value="0" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">التاريخ *</label><input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    </div>
    <div><label class="text-sm">ملاحظة</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></textarea></div>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">تنفيذ التحويل</button>
</form>
@endsection
