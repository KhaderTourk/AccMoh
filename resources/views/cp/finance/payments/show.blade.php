@extends('cp.layout')
@section('title', 'تفاصيل الدفعة')
@section('content')
<div class="max-w-2xl space-y-4">
    <div class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-2">
        <p>العميل: <a href="{{ route('cp.clients.show', $payment->client) }}" class="text-primary font-bold">{{ $payment->client->name }}</a></p>
        <p>المبلغ: <strong>{{ $payment->currency->format($payment->amount) }}</strong></p>
        <p>الطريقة: {{ $payment->paymentMethod->name }}</p>
        <p>المرسل: {{ $payment->payer_name }}</p>
        <p>التاريخ: {{ $payment->payment_date->format('Y-m-d') }}</p>
        @if($payment->is_reversed)<p class="text-rose-600 font-bold">ملغاة</p>@endif
        <h3 class="font-bold pt-3">التوزيع</h3>
        <ul class="text-sm space-y-1">
            @foreach($payment->allocations as $a)
                <li>{{ $a->service->title }} — {{ $payment->currency->format($a->allocated_amount) }}</li>
            @endforeach
        </ul>
        @unless($payment->is_reversed)
        <form method="post" action="{{ route('cp.payments.reverse', $payment) }}" onsubmit="return confirm('إلغاء الدفعة وإرجاع أثرها على الرصيد والمستحقات؟')">
            @csrf
            <button class="mt-4 px-4 py-2 rounded-xl bg-rose-600 text-white text-sm">إلغاء الدفعة (Reversal)</button>
        </form>
        @endunless
    </div>
</div>
@endsection
