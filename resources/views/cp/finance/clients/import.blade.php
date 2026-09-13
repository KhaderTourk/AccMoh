@extends('cp.layout')
@section('title', 'استيراد العملاء من إكسيل')
@section('content')
<div class="max-w-3xl space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">استيراد ملفات الزبائن القديمة</h2>
            <p class="text-sm text-slate-500 mt-1">ارفع ملف إكسيل لكل زبون. الاسم يُقرأ من اسم الملف بالشكل: <span dir="ltr">2026 - كشف حساب - اسم الزبون</span></p>
        </div>
        <a href="{{ route('cp.clients.index') }}" class="px-4 py-2 rounded-xl border">رجوع</a>
    </div>

    <form method="post" action="{{ route('cp.clients.import.preview') }}" enctype="multipart/form-data" class="rounded-2xl bg-white dark:bg-slate-800 border p-6 space-y-4">
        @csrf
        <div>
            <label class="text-sm font-medium block mb-2">ملفات الإكسيل</label>
            <input type="file" name="files[]" accept=".xlsx,.xls,.ods" multiple required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            <p class="text-xs text-slate-500 mt-1">يمكن اختيار عدة ملفات دفعة واحدة. الحد 10MB لكل ملف.</p>
        </div>
        <button class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-primary text-white">
            <span class="material-symbols-outlined">preview</span>
            معاينة قبل الحفظ
        </button>
    </form>

    <div class="rounded-2xl border bg-white dark:bg-slate-800 p-6 text-sm space-y-3">
        <h3 class="font-bold">ما الذي يُقرأ من كل ملف؟</h3>
        <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
            <li>اسم الملف يُفضَّل أن يكون بالشكل: <strong>2026 - كشف حساب - اسم الزبون</strong></li>
            <li><strong>كشف حساب:</strong> الخدمات كأعمدة والشهر (1–12) كصفوف.</li>
            <li><strong>دفعات:</strong> التاريخ، المجموع بالشيكل، وطريقة الدفع في الملاحظات (بنك / جوال / نقدي / بال باي).</li>
            <li><strong>المجموع:</strong> السعر الإجمالي لكل خدمة. صف «دفعات» و«المجموع بالشيكل» لا يُستوردان كخدمات.</li>
            <li><strong>صفحات الخدمات:</strong> أشهر السنة مع المجموع بالشيكل (والدولار إن وُجد). الأشهر ذات المبلغ صفر تُتخطى.</li>
        </ul>
        <p class="text-xs text-slate-500">الدفعات تُسجَّل كدفعات واردة على الزبون. لا يُحفظ شيء قبل تأكيد المعاينة.</p>
    </div>
</div>
@endsection
