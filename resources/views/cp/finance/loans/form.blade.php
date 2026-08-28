@extends('cp.layout')
@section('title', 'تسجيل مدين أو دائن')
@section('content')
<form method="post" action="{{ route('cp.family-loans.store') }}" class="max-w-2xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4">
    @csrf
    <div>
        <label class="text-sm">الفرد *</label>
        <select name="family_member_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            @foreach($members as $m)<option value="{{ $m->id }}" @selected(old('family_member_id', $selectedMemberId)==$m->id)>{{ $m->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="text-sm">النوع *</label>
        <select name="direction" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            @foreach($directions as $d)<option value="{{ $d->value }}" @selected(old('direction', $selectedDirection)==$d->value)>{{ $d->label() }}</option>@endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1">دائن: أخذت منه فيزيد رصيدك. مدين: أعطيته فينقص رصيدك.</p>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm">المبلغ *</label><input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
        <div><label class="text-sm">العملة *</label><select name="currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></div>
        <div><label class="text-sm">طريقة الدفع *</label><select name="payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">@foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
        <div><label class="text-sm">التاريخ *</label><input type="date" name="loan_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></div>
    </div>
    <div><label class="text-sm">ملاحظة</label><textarea name="notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></textarea></div>
    <button class="px-5 py-2 rounded-xl bg-primary text-white">حفظ</button>
</form>
@endsection
