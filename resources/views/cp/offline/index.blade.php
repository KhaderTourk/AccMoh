@extends('cp.layout')

@section('title', 'وضع عدم الاتصال')

@section('content')
<div
    x-data="offlineWorkspace()"
    x-init="init()"
    class="space-y-6"
>
    <div class="rounded-2xl border bg-white dark:bg-slate-800 p-4 flex flex-col lg:flex-row lg:items-center gap-3 justify-between">
        <div class="flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium"
                  :class="online ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'">
                <span class="material-symbols-outlined text-base" x-text="online ? 'wifi' : 'wifi_off'"></span>
                <span x-text="online ? 'متصل' : 'بدون إنترنت — الإدخال محلي'"></span>
            </span>
            <span class="text-sm text-slate-500" x-show="pending > 0">
                بانتظار المزامنة: <strong class="text-primary" x-text="pending"></strong>
            </span>
            <span class="text-xs text-slate-400" x-show="lastSync" x-text="'آخر تحديث للكاش: ' + lastSync"></span>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="pullCache()" :disabled="busy || !online"
                    class="px-4 py-2 rounded-xl border text-sm disabled:opacity-50">تحديث الكاش</button>
            <button type="button" @click="runSync()" :disabled="busy || !online || pending === 0"
                    class="px-4 py-2 rounded-xl bg-primary text-white text-sm disabled:opacity-50">مزامنة الآن</button>
        </div>
    </div>

    <p class="text-sm text-slate-500" x-show="message" x-text="message"
       :class="messageError ? 'text-rose-600' : 'text-emerald-600'"></p>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl bg-slate-900 text-white p-5">
            <p class="text-sm text-slate-300 mb-2">الرصيد المحلي (تقريبي)</p>
            <template x-for="(amount, code) in (snapshot?.balances?.grand || {})" :key="code">
                <p class="text-xl font-extrabold" x-text="code + ': ' + amount"></p>
            </template>
            <p class="text-xs text-amber-300 mt-2" x-show="snapshot?._optimistic">يتضمن عمليات لم تُزامَن بعد</p>
            <p class="text-sm text-slate-400" x-show="!snapshot">لا يوجد كاش بعد. اتصل وحدّث الكاش مرة واحدة.</p>
        </div>
        <template x-for="fund in (snapshot?.balances?.funds || [])" :key="fund.id">
            <div class="rounded-2xl border bg-white dark:bg-slate-800 p-5">
                <p class="font-bold mb-2" x-text="fund.name"></p>
                <template x-for="(amount, code) in (fund.totals || {})" :key="code">
                    <p class="text-sm" x-text="code + ': ' + amount"></p>
                </template>
            </div>
        </template>
    </section>

    <div class="flex flex-wrap gap-2">
        <template x-for="t in tabs" :key="t.id">
            <button type="button" @click="tab = t.id"
                    class="px-4 py-2 rounded-xl text-sm font-medium border"
                    :class="tab === t.id ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-slate-800'"
                    x-text="t.label"></button>
        </template>
    </div>

    <form @submit.prevent="submitPayment()" class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4 max-w-3xl">
        <h3 class="font-bold" x-text="tab === 'outgoing' ? 'دفعة صادرة' : 'دفعة واردة'"></h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs">التاريخ *</label>
                <input type="date" x-model="form.occurred_on" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">الاسم *</label>
                <input type="text" x-model="form.name" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">الدرج *</label>
                <select x-model="form.fund_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="f in (snapshot?.catalog?.funds || [])" :key="f.id">
                        <option :value="f.id" x-text="f.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">طريقة الدفع *</label>
                <select x-model="form.payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="m in (snapshot?.catalog?.payment_methods || [])" :key="m.id">
                        <option :value="m.id" x-text="m.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">العملة *</label>
                <select x-model="form.currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="c in (snapshot?.catalog?.currencies || [])" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">المبلغ *</label>
                <input type="number" step="0.01" min="0.01" x-model="form.amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">اسم صاحب الحساب</label>
                <input type="text" x-model="form.account_holder_name" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">ربط بطرف</label>
                <select x-model="form.party_key" @change="onPartyChange()" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <option value="">اسم حر</option>
                    <template x-for="c in (snapshot?.clients || [])" :key="'c'+c.id">
                        <option :value="'client:'+c.id" x-text="'زبون: ' + c.name"></option>
                    </template>
                    <template x-for="p in (snapshot?.persons || [])" :key="'p'+p.id">
                        <option :value="'person:'+p.id" x-text="'شخص: ' + p.name"></option>
                    </template>
                    <template x-for="v in (snapshot?.vendors || [])" :key="'v'+v.id">
                        <option :value="'vendor:'+v.id" x-text="v.name"></option>
                    </template>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="text-xs">ملاحظة</label>
                <textarea x-model="form.notes" rows="2" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700"></textarea>
            </div>
        </div>
        <button class="px-5 py-2 rounded-xl bg-primary text-white" :disabled="busy">حفظ (محلي / مزامنة)</button>
    </form>

    <section class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <div class="px-4 py-3 border-b font-bold flex justify-between">
            <span>طابور المزامنة (Outbox)</span>
            <button type="button" class="text-sm text-primary" @click="reloadOutbox()">تحديث القائمة</button>
        </div>
        <div class="divide-y dark:divide-slate-700 max-h-80 overflow-y-auto">
            <template x-if="outbox.length === 0">
                <p class="p-6 text-sm text-slate-500 text-center">لا توجد عمليات معلّقة.</p>
            </template>
            <template x-for="item in outbox" :key="item.operation_id">
                <div class="px-4 py-3 text-sm flex justify-between gap-3 items-start">
                    <div>
                        <p class="font-medium" x-text="typeLabel(item.type)"></p>
                        <p class="text-xs text-slate-500" x-text="item.payload?.name || item.operation_id"></p>
                        <p class="text-xs text-rose-600" x-show="item.last_error" x-text="item.last_error"></p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="text-xs px-2 py-1 rounded h-fit"
                              :class="item.status === 'pending' ? 'bg-amber-500/15 text-amber-700' : 'bg-rose-500/15 text-rose-700'"
                              x-text="item.status"></span>
                        <button type="button" class="text-xs text-rose-600" @click="discardOutboxItem(item)"
                                :disabled="busy">حذف من الطابور</button>
                    </div>
                </div>
            </template>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/cp-offline.js') }}"></script>
<script>
function offlineWorkspace() {
    const today = new Date().toISOString().slice(0, 10);
    return {
        online: navigator.onLine,
        busy: false,
        message: '',
        messageError: false,
        snapshot: null,
        pending: 0,
        lastSync: '',
        outbox: [],
        tab: 'incoming',
        tabs: [
            { id: 'incoming', label: 'دفعة واردة' },
            { id: 'outgoing', label: 'دفعة صادرة' },
        ],
        form: { occurred_on: today, name: '', fund_id: '', payment_method_id: '', currency_id: '', amount: '', account_holder_name: '', notes: '', party_key: '' },

        async init() {
            window.addEventListener('online', () => { this.online = true; this.flash('عادت الشبكة — يمكنك المزامنة'); this.autoSync(); });
            window.addEventListener('offline', () => { this.online = false; this.flash('أنت بدون إنترنت — الحفظ سيكون محلياً', true); });
            this.snapshot = await AccmaOffline.getSnapshot();
            this.seedDefaults();
            await this.reloadOutbox();
            if (this.online && !this.snapshot) {
                await this.pullCache();
            } else if (this.online) {
                this.autoSync();
            }
        },

        seedDefaults() {
            const currencies = this.snapshot?.catalog?.currencies || [];
            const ils = currencies.find(c => c.code === 'ILS');
            const cur = ils || currencies[0];
            const method = this.snapshot?.catalog?.payment_methods?.[0];
            const family = (this.snapshot?.catalog?.funds || []).find(f => f.slug === 'family');
            const business = (this.snapshot?.catalog?.funds || []).find(f => f.slug === 'business');
            const fund = this.tab === 'outgoing' ? (family || business) : (business || family);
            if (cur) this.form.currency_id = String(cur.id);
            if (method) this.form.payment_method_id = String(method.id);
            if (fund) this.form.fund_id = String(fund.id);
        },

        flash(msg, isError = false) {
            this.message = msg;
            this.messageError = isError;
        },

        typeLabel(type) {
            return ({ incoming_payment: 'دفعة واردة', outgoing_payment: 'دفعة صادرة' })[type] || type;
        },

        async reloadOutbox() {
            this.outbox = await AccmaOffline.listOutbox();
            this.pending = await AccmaOffline.pendingCount();
            this.snapshot = await AccmaOffline.getSnapshot();
            const metaTime = this.snapshot?.server_time;
            this.lastSync = metaTime ? new Date(metaTime).toLocaleString('ar') : '';
        },

        async discardOutboxItem(item) {
            if (!confirm('حذف هذه العملية من طابور المزامنة؟ لن تُرسل للسيرفر.')) return;
            this.busy = true;
            try {
                await AccmaOffline.idbDeleteOutbox(item.operation_id);
                if (this.online) {
                    try { await AccmaOffline.refreshCache(); } catch (_) {}
                }
                await this.reloadOutbox();
                this.flash('تم حذف العملية من الطابور');
            } catch (e) {
                this.flash(e.message || 'تعذّر الحذف', true);
            } finally {
                this.busy = false;
            }
        },

        async pullCache() {
            if (!this.online) return this.flash('يحتاج تحديث الكاش لاتصالاً بالإنترنت', true);
            this.busy = true;
            try {
                this.snapshot = await AccmaOffline.refreshCache();
                this.seedDefaults();
                this.flash('تم تحديث الكاش من السيرفر');
                await this.reloadOutbox();
            } catch (e) {
                this.flash(e.message || 'فشل تحديث الكاش', true);
            } finally {
                this.busy = false;
            }
        },

        async runSync() {
            if (!this.online) return this.flash('لا يوجد اتصال', true);
            this.busy = true;
            try {
                const res = await AccmaOffline.syncPending();
                this.snapshot = res.snapshot || await AccmaOffline.getSnapshot();
                await this.reloadOutbox();
                const failed = (res.results || []).filter(r => r.status === 'failed').length;
                this.flash(failed ? `تمت مزامنة ${res.synced} — فشل ${failed}` : `تمت مزامنة ${res.synced} عملية بنجاح`, !!failed);
            } catch (e) {
                this.flash(e.message || 'فشلت المزامنة', true);
            } finally {
                this.busy = false;
            }
        },

        async autoSync() {
            if (!this.online) return;
            const n = await AccmaOffline.pendingCount();
            if (n > 0) await this.runSync();
        },

        onPartyChange() {
            if (!this.form.party_key) return;
            const [type, id] = this.form.party_key.split(':');
            const list = type === 'client' ? this.snapshot?.clients : (type === 'person' ? this.snapshot?.persons : this.snapshot?.vendors);
            const row = (list || []).find(x => String(x.id) === String(id));
            if (row) this.form.name = row.name;
            const family = (this.snapshot?.catalog?.funds || []).find(f => f.slug === 'family');
            const business = (this.snapshot?.catalog?.funds || []).find(f => f.slug === 'business');
            if (type === 'person' && family) this.form.fund_id = String(family.id);
            if (type !== 'person' && business) this.form.fund_id = String(business.id);
        },

        async submitPayment() {
            const pay = parseFloat(this.form.amount) || 0;
            if (pay <= 0) return this.flash('أدخل مبلغاً أكبر من صفر', true);
            const payload = {
                name: this.form.name,
                fund_id: Number(this.form.fund_id),
                payment_method_id: Number(this.form.payment_method_id),
                currency_id: Number(this.form.currency_id),
                amount: Number(this.form.amount),
                account_holder_name: this.form.account_holder_name || null,
                occurred_on: this.form.occurred_on,
                notes: this.form.notes || null,
            };
            if (this.form.party_key) {
                const [type, id] = this.form.party_key.split(':');
                payload.party_type = type;
                payload.party_id = Number(id);
            }
            await this.saveOp(this.tab === 'outgoing' ? 'outgoing_payment' : 'incoming_payment', payload);
            this.form.amount = '';
            this.form.notes = '';
        },

        async saveOp(type, payload) {
            this.busy = true;
            try {
                if (this.online) {
                    await AccmaOffline.queueAndOptimistic(type, payload);
                    await this.runSync();
                } else {
                    await AccmaOffline.queueAndOptimistic(type, payload);
                    await this.reloadOutbox();
                    this.flash('حُفظت محلياً وستُزامَن عند توفر الإنترنت');
                }
            } catch (e) {
                this.flash(e.message || 'فشل الحفظ', true);
            } finally {
                this.busy = false;
            }
        },
    }
}
</script>
@endpush
