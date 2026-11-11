{{-- «وقّف»: آخر شهر من قائمة + آخر يوم شغل (صورة نسخة 5، 11/11/2026). بيتعبّى من cp-monthly-sheet.js --}}
<div class="cl-sheet" id="mpStopSheet" hidden>
    <form class="cl-dialog" id="mpStopForm" method="post" novalidate role="dialog" aria-modal="true" aria-labelledby="mpStopTitle">
        @csrf
        <h2 id="mpStopTitle"></h2>
        <p class="mp-who" id="mpStopWho"></p>
        <div class="cl-field"><label>آخر شهر شغل</label>
            <div class="mp-dd"><button type="button" class="mp-dd-btn" id="mpStopMonth"></button><div class="cl-menu mp-menu mp-dd-menu" id="mpStopMonthMenu" hidden></div></div></div>
        <div class="cl-field"><label id="mpStopDayL"></label><div class="mp-days" id="mpStopDays"></div></div>
        <input type="hidden" name="ends_on" id="mpStopDate">
        <div class="mp-calc" id="mpStopCalc"></div>
        <div class="cl-field"><label for="mpStopAmt" id="mpStopAmtL">مبلغ آخر شهر</label><input id="mpStopAmt" name="last_amount" inputmode="decimal" dir="ltr"></div>
        <div class="cl-field"><label for="mpStopNotes">ملاحظات</label><textarea id="mpStopNotes" name="notes" rows="2" placeholder="اختياري"></textarea></div>
        <div class="cl-actions">
            <button type="submit" class="cp-btn cp-btn-primary" id="mpStopSave">وقّف</button>
            <button type="button" class="cp-btn cp-btn-ghost" id="mpStopCancel">إلغاء</button>
        </div>
    </form>
</div>
<script src="{{ asset('assets/js/cp-monthly-sheet.js') }}?v={{ @filemtime(public_path('assets/js/cp-monthly-sheet.js')) }}"></script>
