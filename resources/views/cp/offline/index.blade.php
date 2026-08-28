@extends('cp.layout')

@section('title', 'وضع عدم الاتصال')

@section('content')
<div
    x-data="offlineWorkspace()"
    x-init="init()"
    class="space-y-6"
>
    {{-- Status bar --}}
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

    {{-- Balances --}}
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

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2">
        <template x-for="t in tabs" :key="t.id">
            <button type="button" @click="tab = t.id"
                    class="px-4 py-2 rounded-xl text-sm font-medium border"
                    :class="tab === t.id ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-slate-800'"
                    x-text="t.label"></button>
        </template>
    </div>

    {{-- Payment --}}
    <form x-show="tab === 'payment'" @submit.prevent="submitPayment()" class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4 max-w-3xl">
        <h3 class="font-bold">استلام دفعة عميل</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs">العميل</label>
                <select x-model="payment.client_id" @change="onClientChange()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <option value="">اختر</option>
                    <template x-for="c in (snapshot?.clients || [])" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">العملة</label>
                <select x-model="payment.currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="c in (snapshot?.catalog?.currencies || [])" :key="c.id">
                        <option :value="c.id" x-text="c.code + ' — ' + c.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">المبلغ</label>
                <input type="number" step="0.01" min="0.01" x-model="payment.amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">طريقة الدفع</label>
                <select x-model="payment.payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="m in (snapshot?.catalog?.payment_methods || [])" :key="m.id">
                        <option :value="m.id" x-text="m.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">التاريخ</label>
                <input type="date" x-model="payment.payment_date" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">المرسل</label>
                <input type="text" x-model="payment.payer_name" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
        </div>
        <div class="rounded-xl border border-dashed p-4 space-y-2">
            <p class="font-medium text-sm">المستحق على العميل</p>
            <p class="text-lg font-extrabold text-amber-600 dark:text-amber-300" x-text="clientDueFormatted()"></p>
            <p class="text-sm text-slate-500" x-show="payment.client_id && clientDue() <= 0 && clientDue() >= 0">لا يوجد مستحق حالياً — يمكن تسجيل عربون قبل تقديم الخدمة.</p>
        <p class="text-sm text-emerald-600" x-show="payment.client_id && clientDue() < 0" x-text="'عربون / رصيد مدفوع مقدماً: ' + Math.abs(clientDue()).toFixed(2)"></p>
            <p class="text-xs text-slate-500">تُخصم الدفعة من إجمالي المستحق على العميل، وليس من خدمة بعينها.</p>
        </div>
        <button class="px-5 py-2 rounded-xl bg-primary text-white" :disabled="busy">حفظ (محلي / مزامنة)</button>
    </form>

    {{-- Expense --}}
    <form x-show="tab === 'expense'" @submit.prevent="submitExpense()" class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4 max-w-3xl">
        <h3 class="font-bold">إضافة مصروف</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs">الصندوق</label>
                <select x-model="expense.fund_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="f in (snapshot?.catalog?.funds || [])" :key="f.id">
                        <option :value="f.id" x-text="f.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">التصنيف</label>
                <select x-model="expense.expense_category_id" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <option value="">—</option>
                    <template x-for="c in (snapshot?.catalog?.expense_categories || [])" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="text-xs">الوصف</label>
                <input type="text" x-model="expense.description" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">المبلغ</label>
                <input type="number" step="0.01" min="0.01" x-model="expense.amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">العملة</label>
                <select x-model="expense.currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="c in (snapshot?.catalog?.currencies || [])" :key="c.id">
                        <option :value="c.id" x-text="c.code"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">طريقة الدفع</label>
                <select x-model="expense.payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="m in (snapshot?.catalog?.payment_methods || [])" :key="m.id">
                        <option :value="m.id" x-text="m.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">التاريخ</label>
                <input type="date" x-model="expense.expense_date" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
        </div>
        <button class="px-5 py-2 rounded-xl bg-primary text-white" :disabled="busy">حفظ</button>
    </form>

    {{-- Loan --}}
    <form x-show="tab === 'loan'" @submit.prevent="submitLoan()" class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4 max-w-3xl">
        <h3 class="font-bold">تسجيل قرض عائلي سريع</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs">الفرد</label>
                <select x-model="loan.family_member_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <option value="">اختر</option>
                    <template x-for="m in (snapshot?.family_members || [])" :key="m.id">
                        <option :value="m.id" x-text="m.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">الاتجاه</label>
                <select x-model="loan.direction" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <option value="borrowed">اقتراض (أنا مدين)</option>
                    <option value="lent">إقراض (مدين لي)</option>
                </select>
            </div>
            <div>
                <label class="text-xs">المبلغ</label>
                <input type="number" step="0.01" min="0.01" x-model="loan.amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">العملة</label>
                <select x-model="loan.currency_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="c in (snapshot?.catalog?.currencies || [])" :key="c.id">
                        <option :value="c.id" x-text="c.code"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">طريقة الدفع</label>
                <select x-model="loan.payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="m in (snapshot?.catalog?.payment_methods || [])" :key="m.id">
                        <option :value="m.id" x-text="m.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">التاريخ</label>
                <input type="date" x-model="loan.loan_date" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
        </div>
        <button class="px-5 py-2 rounded-xl bg-primary text-white" :disabled="busy">حفظ</button>
    </form>

    {{-- Repay --}}
    <form x-show="tab === 'repay'" @submit.prevent="submitRepay()" class="rounded-2xl border bg-white dark:bg-slate-800 p-6 space-y-4 max-w-3xl">
        <h3 class="font-bold">سداد قرض عائلي سريع</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-xs">الفرد</label>
                <select x-model="repay.family_member_id" @change="filterLoans()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <option value="">اختر</option>
                    <template x-for="m in (snapshot?.family_members || [])" :key="m.id">
                        <option :value="m.id" x-text="m.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">الاتجاه</label>
                <select x-model="repay.direction" @change="filterLoans()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <option value="borrowed">أسدد ديني</option>
                    <option value="lent">أستلم سداداً</option>
                </select>
            </div>
            <div>
                <label class="text-xs">المبلغ</label>
                <input type="number" step="0.01" min="0.01" x-model="repay.amount" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
            <div>
                <label class="text-xs">العملة</label>
                <select x-model="repay.currency_id" @change="filterLoans()" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="c in (snapshot?.catalog?.currencies || [])" :key="c.id">
                        <option :value="c.id" x-text="c.code"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">طريقة الدفع</label>
                <select x-model="repay.payment_method_id" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    <template x-for="m in (snapshot?.catalog?.payment_methods || [])" :key="m.id">
                        <option :value="m.id" x-text="m.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-xs">التاريخ</label>
                <input type="date" x-model="repay.repayment_date" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
            </div>
        </div>
        <div class="rounded-xl border border-dashed p-4 space-y-2">
            <div class="flex justify-between">
                <p class="text-sm font-medium">توزيع على القروض</p>
                <button type="button" class="text-sm text-primary" @click="autoAllocateLoans()">توزيع تلقائي</button>
            </div>
            <template x-for="l in repayLoans" :key="l.id">
                <div class="grid grid-cols-12 gap-2 text-sm items-center">
                    <div class="col-span-7" x-text="l.loan_date + ' — متبقي ' + l.remaining"></div>
                    <div class="col-span-5">
                        <input type="number" step="0.01" min="0" x-model="l.allocate" class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700">
                    </div>
                </div>
            </template>
        </div>
        <button class="px-5 py-2 rounded-xl bg-primary text-white" :disabled="busy">حفظ</button>
    </form>

    {{-- Outbox --}}
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
                        <p class="text-xs text-slate-500" x-text="item.operation_id"></p>
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
        tab: 'payment',
        tabs: [
            { id: 'payment', label: 'دفعة عميل' },
            { id: 'expense', label: 'مصروف' },
            { id: 'loan', label: 'قرض' },
            { id: 'repay', label: 'سداد' },
        ],
        businessEnabled: true,
        payment: { client_id: '', currency_id: '', amount: '', payment_method_id: '', payment_date: today, payer_name: '' },
        expense: { fund_id: '', expense_category_id: '', description: '', amount: '', currency_id: '', payment_method_id: '', expense_date: today },
        loan: { family_member_id: '', direction: 'borrowed', amount: '', currency_id: '', payment_method_id: '', loan_date: today },
        repay: { family_member_id: '', direction: 'borrowed', amount: '', currency_id: '', payment_method_id: '', repayment_date: today },
        repayLoans: [],

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
            this.businessEnabled = this.snapshot?.features?.business_enabled !== false;
            if (!this.businessEnabled) {
                this.tabs = this.tabs.filter(t => t.id !== 'payment');
                if (this.tab === 'payment') this.tab = 'expense';
            }
            const currencies = this.snapshot?.catalog?.currencies || [];
            const ils = currencies.find(c => c.code === 'ILS');
            const cur = ils || currencies[0];
            const method = this.snapshot?.catalog?.payment_methods?.[0];
            const fund = this.snapshot?.catalog?.funds?.[0];
            if (cur) {
                this.payment.currency_id = String(cur.id);
                this.expense.currency_id = String(cur.id);
                this.loan.currency_id = String(cur.id);
                this.repay.currency_id = String(cur.id);
            }
            if (method) {
                this.payment.payment_method_id = String(method.id);
                this.expense.payment_method_id = String(method.id);
                this.loan.payment_method_id = String(method.id);
                this.repay.payment_method_id = String(method.id);
            }
            if (fund) this.expense.fund_id = String(fund.id);
        },

        flash(msg, isError = false) {
            this.message = msg;
            this.messageError = isError;
        },

        typeLabel(type) {
            return ({
                client_payment: 'دفعة عميل',
                expense: 'مصروف',
                family_loan: 'قرض عائلي',
                family_loan_repayment: 'سداد عائلي',
            })[type] || type;
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

        onClientChange() {
            const c = (this.snapshot?.clients || []).find(x => String(x.id) === String(this.payment.client_id));
            if (c) this.payment.payer_name = c.contact_name || c.name;
        },

        clientDue() {
            const c = (this.snapshot?.clients || []).find(x => String(x.id) === String(this.payment.client_id));
            const cur = (this.snapshot?.catalog?.currencies || []).find(x => String(x.id) === String(this.payment.currency_id));
            if (!c || !cur) return 0;
            return parseFloat(c.outstanding?.[cur.code] || 0);
        },

        clientDueFormatted() {
            const cur = (this.snapshot?.catalog?.currencies || []).find(x => String(x.id) === String(this.payment.currency_id));
            const due = this.clientDue().toFixed(2);
            if (!cur) return due;
            return cur.code === 'USD' ? (cur.symbol + due) : (due + ' ' + (cur.symbol || cur.code));
        },

        filterLoans() {
            this.repayLoans = (this.snapshot?.open_loans || [])
                .filter(l =>
                    String(l.family_member_id) === String(this.repay.family_member_id) &&
                    String(l.currency_id) === String(this.repay.currency_id) &&
                    l.direction === this.repay.direction
                )
                .map(l => ({ ...l, allocate: 0 }));
        },

        autoAllocateLoans() {
            let left = parseFloat(this.repay.amount) || 0;
            this.repayLoans.forEach(l => {
                const rem = parseFloat(l.remaining) || 0;
                const take = Math.min(left, rem);
                l.allocate = take.toFixed(2);
                left = Math.round((left - take) * 100) / 100;
            });
        },

        async submitPayment() {
            const pay = parseFloat(this.payment.amount) || 0;
            if (pay <= 0) {
                return this.flash('أدخل مبلغاً أكبر من صفر', true);
            }
            const payload = {
                client_id: Number(this.payment.client_id),
                amount: Number(this.payment.amount),
                currency_id: Number(this.payment.currency_id),
                payment_method_id: Number(this.payment.payment_method_id),
                payment_date: this.payment.payment_date,
                payer_name: this.payment.payer_name || null,
            };
            await this.saveOp('client_payment', payload);
            this.payment.amount = '';
        },

        async submitExpense() {
            const payload = {
                fund_id: Number(this.expense.fund_id),
                expense_category_id: this.expense.expense_category_id ? Number(this.expense.expense_category_id) : null,
                description: this.expense.description,
                amount: Number(this.expense.amount),
                currency_id: Number(this.expense.currency_id),
                payment_method_id: Number(this.expense.payment_method_id),
                expense_date: this.expense.expense_date,
            };
            await this.saveOp('expense', payload);
            this.expense.description = '';
            this.expense.amount = '';
        },

        async submitLoan() {
            const payload = {
                family_member_id: Number(this.loan.family_member_id),
                direction: this.loan.direction,
                amount: Number(this.loan.amount),
                currency_id: Number(this.loan.currency_id),
                payment_method_id: Number(this.loan.payment_method_id),
                loan_date: this.loan.loan_date,
            };
            await this.saveOp('family_loan', payload);
            this.loan.amount = '';
        },

        async submitRepay() {
            let allocations = this.repayLoans
                .filter(l => parseFloat(l.allocate) > 0)
                .map(l => ({ family_loan_id: Number(l.id), amount: Number(l.allocate) }));
            let sum = allocations.reduce((t, a) => t + a.amount, 0);
            if (sum < 0.001 && (parseFloat(this.repay.amount) || 0) > 0) {
                this.autoAllocateLoans();
                allocations = this.repayLoans
                    .filter(l => parseFloat(l.allocate) > 0)
                    .map(l => ({ family_loan_id: Number(l.id), amount: Number(l.allocate) }));
                sum = allocations.reduce((t, a) => t + a.amount, 0);
            }
            if (Math.abs(sum - (parseFloat(this.repay.amount) || 0)) > 0.001) {
                return this.flash('مجموع التوزيع يجب أن يساوي مبلغ السداد', true);
            }
            const payload = {
                family_member_id: Number(this.repay.family_member_id),
                direction: this.repay.direction,
                amount: Number(this.repay.amount),
                currency_id: Number(this.repay.currency_id),
                payment_method_id: Number(this.repay.payment_method_id),
                repayment_date: this.repay.repayment_date,
                allocations,
            };
            await this.saveOp('family_loan_repayment', payload);
            this.repay.amount = '';
            this.filterLoans();
        },

        async saveOp(type, payload) {
            this.busy = true;
            try {
                if (this.online) {
                    // Try direct sync first for better UX when online
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
