{{-- تسوية رصيد (نافذة مشتركة بين «الأدراج والأرصدة» وصفحة الدرج). أي زر [data-settle][data-name] بيفتحها.
     بيحتاج $wizard (رصيد كل درج بكل عملة) و$currencies. --}}
{{-- تسوية رصيد (12/11/2026): نفس شكل نافذة التحويل. العملة أزرار مش قائمة، وبيبيّن اللي بالنظام والفرق قبل الحفظ. --}}
<div class="cl-sheet tw-sheet" id="blSheet" hidden>
    <form class="tw-dialog" method="post" action="{{ route('cp.balances.settle') }}" role="dialog" aria-modal="true" aria-labelledby="blSheetT" id="blSettle">
        @csrf
        <input type="hidden" name="payment_method_id" id="blMethod">
        <input type="hidden" name="currency_id" id="blCur">
        <div class="tw-head">
            <h2 id="blSheetT">تسوية رصيد</h2>
            <button type="button" class="tw-x" data-close aria-label="إغلاق">@include('cp.partials.bs-icon', ['name' => 'close', 'size' => 18])</button>
        </div>
        <div class="tw-body">
            <span class="tw-l">العملة</span>
            <div class="tw-seg" id="blCurSeg">
                @foreach($currencies as $c)<button type="button" data-cur="{{ $c->id }}" data-code="{{ $c->code }}">{{ \App\Support\DrawerPlaces::curName($c->code, $c->name) }} {{ $c->symbol }}</button>@endforeach
            </div>
            <div class="tw-rv"><span>اللي بالنظام</span><span id="blHave" dir="ltr"></span></div>
            <label class="tw-l" for="blActual">الموجود فعلياً</label>
            <input id="blActual" name="actual" class="tw-big" inputmode="decimal" dir="ltr" placeholder="0.00" autocomplete="off" required>
            <div class="tw-rv bl-diff" id="blDiffRow" hidden><span>الفرق اللي رح ينسجل</span><span id="blDiff" dir="ltr"></span></div>
            <label class="tw-l" for="blNotes">ليش الفرق؟</label>
            <textarea id="blNotes" name="notes" class="tw-in tw-area" rows="2" required placeholder="ملاحظة إجبارية" maxlength="500"></textarea>
            <small class="tw-err" id="blErr">@error('actual'){{ $message }}@enderror @error('notes'){{ $message }}@enderror</small>
        </div>
        <div class="tw-foot">
            <span class="tw-grow"></span>
            <button type="submit" class="tw-pri" id="blSave" disabled>سجّل التسوية</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const BAL = @json(collect($wizard['methods'])->mapWithKeys(fn ($m) => [$m['id'] => $m['bal']]));
    const SYM = @json($currencies->mapWithKeys(fn ($c) => [$c->code => $c->symbol]));
    const fmt = (v) => (v < 0 ? '−' : '') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (s) => { const t = String(s || '').replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/,/g, '').replace('٫', '.').trim(); const v = parseFloat(t); return t !== '' && isFinite(v) ? v : NaN; };
    const shut = () => { $('blSheet').hidden = true; document.body.style.overflow = ''; };
    let method = null, code = null;
    function paint() {
        document.querySelectorAll('#blCurSeg [data-cur]').forEach((b) => b.classList.toggle('on', b.dataset.code === code));
        const have = (BAL[method] || {})[code] || 0;
        $('blHave').textContent = (SYM[code] || code) + ' ' + fmt(have);
        const a = num($('blActual').value);
        const diff = isNaN(a) ? NaN : Math.round((a - have) * 100) / 100;
        $('blDiffRow').hidden = isNaN(diff);
        if (!isNaN(diff)) { $('blDiff').textContent = (diff > 0 ? '+' : '') + (SYM[code] || code) + ' ' + fmt(diff); $('blDiff').className = diff < 0 ? 'due' : ''; }
        $('blSave').disabled = isNaN(diff) || Math.abs(diff) < 0.005 || !$('blNotes').value.trim();
        $('blErr').textContent = !isNaN(diff) && Math.abs(diff) < 0.005 ? 'الموجود نفس اللي بالنظام، ما في فرق.' : '';
    }
    $('blCurSeg').addEventListener('click', (e) => { const b = e.target.closest('[data-cur]'); if (!b) return; code = b.dataset.code; $('blCur').value = b.dataset.cur; paint(); $('blActual').focus(); });
    $('blActual').addEventListener('input', paint);
    $('blNotes').addEventListener('input', paint);
    document.addEventListener('click', (e) => {
        const s = e.target.closest('[data-settle]');
        if (s) {
            const menu = s.closest('.cl-menu'); if (menu) menu.hidden = true;
            method = +s.dataset.settle;
            $('blMethod').value = method; $('blSheetT').textContent = 'تسوية رصيد · ' + s.dataset.name;
            const first = document.querySelector('#blCurSeg [data-cur]');
            code = first.dataset.code; $('blCur').value = first.dataset.cur;
            $('blActual').value = ''; $('blNotes').value = ''; paint();
            $('blSheet').hidden = false; document.body.style.overflow = 'hidden'; setTimeout(() => $('blActual').focus(), 40);
            return;
        }
        if (e.target.closest('#blSheet [data-close]') || e.target.id === 'blSheet') shut();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('blSheet').hidden) shut(); });
})();
</script>
@endpush
