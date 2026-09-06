@extends('cp.layout')
@section('title', $payment->direction->label())
@section('content')
<div class="max-w-xl rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-3 {{ $payment->direction->rowClass() }}">
    <h2 class="text-xl font-bold {{ $payment->direction->colorClass() }}">{{ $payment->direction->label() }}</h2>
    <p>الاسم: {{ $payment->name }}</p>
    <p>التاريخ: {{ $payment->occurred_on->format('Y-m-d') }}</p>
    <p>الدرج: {{ $payment->fund->name }}</p>
    <p>طريقة الدفع: {{ $payment->paymentMethod->name }}</p>
    <p>المبلغ: <strong class="{{ $payment->direction->colorClass() }}">{{ $payment->currency->format($payment->amount) }}</strong></p>
    @if($payment->isFx())
        <p class="text-sm text-slate-500">{{ $payment->fxCurrency?->format($payment->source_amount) }} × {{ $payment->formattedExchangeRate() }}</p>
    @endif
    @if($payment->account_holder_name)
        <p>صاحب الحساب: {{ $payment->account_holder_name }}</p>
    @endif
    @include('cp.partials.note-card', ['notes' => $payment->notes])
    @if($payment->is_reversed)
        <p class="text-rose-600 text-sm">ملغاة</p>
    @endif
</div>
@endsection
