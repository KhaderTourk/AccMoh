<?php

namespace App\Support;

use App\Models\ApiOperation;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\Role;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

/**
 * Turns the raw before/after snapshots of an API operation into readable Arabic text for the CP.
 * Display only: it never changes data.
 */
class ApiChangePresenter
{
    public const TABLES = [
        'clients' => 'زبون', 'client_services' => 'خدمة زبون', 'client_goods_takes' => 'منتج مأخوذ',
        'cash_payments' => 'دفعة', 'client_payments' => 'دفعة زبون', 'ledger_entries' => 'قيد مالي',
        'fund_transfers' => 'تحويل', 'party_balance_memos' => 'رصيد مرحّل', 'family_members' => 'شخص',
        'vendors' => 'موظف/مورد', 'vendor_charges' => 'مستحق', 'service_types' => 'نوع خدمة',
        'users' => 'مستخدم', 'roles' => 'دور', 'role_permission' => 'صلاحيات دور',
        'sync_operations' => 'عملية مزامنة', 'payment_allocations' => 'توزيع دفعة',
    ];

    /** Rows the system writes by itself alongside the main record. */
    public const INTERNAL_TABLES = ['ledger_entries', 'sync_operations', 'payment_allocations'];

    public const FIELDS = [
        'name' => 'الاسم', 'title' => 'العنوان', 'description' => 'الوصف', 'notes' => 'ملاحظات',
        'amount' => 'المبلغ', 'fee_amount' => 'العمولة', 'source_amount' => 'المبلغ الأصلي',
        'allocated_amount' => 'المبلغ المخصّص', 'default_price' => 'السعر الافتراضي', 'price' => 'السعر',
        'quantity' => 'الكمية', 'currency_id' => 'العملة', 'fx_currency_id' => 'عملة الصرف',
        'exchange_rate' => 'سعر الصرف', 'direction' => 'الاتجاه', 'party_type' => 'نوع الطرف',
        'party_id' => 'الطرف', 'client_id' => 'الزبون', 'family_member_id' => 'الشخص',
        'vendor_id' => 'الموظف/المورد', 'service_type_id' => 'نوع الخدمة', 'user_id' => 'المستخدم',
        'role_id' => 'الدور', 'payment_method_id' => 'طريقة الدفع', 'from_payment_method_id' => 'من طريقة',
        'to_payment_method_id' => 'إلى طريقة', 'fund_id' => 'الصندوق', 'phone' => 'الهاتف',
        'email' => 'البريد الإلكتروني', 'contact_name' => 'اسم جهة الاتصال', 'company_name' => 'اسم الشركة',
        'account_holder_name' => 'صاحب الحساب', 'job_title' => 'المسمى الوظيفي', 'is_active' => 'نشط',
        'status' => 'الحالة', 'type' => 'النوع', 'category' => 'الفئة', 'reason' => 'السبب',
        'payment_date' => 'تاريخ الدفعة', 'service_date' => 'تاريخ الخدمة', 'charge_date' => 'تاريخ الاستحقاق',
        'memo_date' => 'تاريخ القيد', 'taken_on' => 'تاريخ الأخذ', 'occurred_on' => 'تاريخ الحركة',
        'transfer_date' => 'تاريخ التحويل', 'expense_date' => 'تاريخ المصروف', 'payer_name' => 'اسم الدافع',
        'payee' => 'المستفيد', 'relationship' => 'صلة القرابة', 'work_description' => 'وصف العمل',
        'is_reversed' => 'ملغاة', 'reversed_at' => 'تاريخ الإلغاء', 'is_reversal' => 'قيد عكسي',
        'code' => 'الرمز', 'symbol' => 'رمز العملة', 'transaction_type' => 'نوع الحركة', 'slug' => 'المعرّف',
    ];

    /** Bookkeeping columns: shown only on request. */
    public const TECHNICAL = [
        'id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'password', 'remember_token',
        'ledger_group_id', 'group_id', 'leg', 'source_type', 'source_id', 'entity_type', 'entity_id',
        'reverses_entry_id', 'sort_order', 'payload', 'action', 'related', 'fund_slug',
    ];

    protected const SECRET = ['password', 'remember_token'];

    protected const AMOUNTS = ['amount', 'fee_amount', 'source_amount', 'allocated_amount', 'default_price', 'price'];

    protected const BOOLEANS = ['is_active', 'is_reversed', 'is_reversal'];

    protected const LOOKUPS = [
        'currency_id' => 'currency', 'fx_currency_id' => 'currency', 'client_id' => 'client',
        'family_member_id' => 'person', 'vendor_id' => 'vendor', 'service_type_id' => 'service_type',
        'user_id' => 'user', 'role_id' => 'role', 'payment_method_id' => 'payment_method',
        'from_payment_method_id' => 'payment_method', 'to_payment_method_id' => 'payment_method', 'fund_id' => 'fund',
    ];

    /** @var array<string, array<int|string, mixed>> */
    protected array $names = [];

    /**
     * Load every name the given operations refer to with one query per kind.
     *
     * @param  iterable<ApiOperation>  $operations
     */
    public function prime(iterable $operations): static
    {
        $ids = [];
        foreach ($operations as $operation) {
            foreach ($operation->changes ?? [] as $change) {
                foreach ([$change['before'] ?? null, $change['after'] ?? null] as $row) {
                    if (! is_array($row) || ($change['op'] ?? '') === 'pivot') {
                        continue;
                    }
                    foreach (self::LOOKUPS as $column => $kind) {
                        if (filled($row[$column] ?? null)) {
                            $ids[$kind][] = $row[$column];
                        }
                    }
                    if (filled($row['party_id'] ?? null) && ($kind = $this->partyKind($row['party_type'] ?? null))) {
                        $ids[$kind][] = $row['party_id'];
                    }
                }
                if (($change['op'] ?? '') !== 'pivot' && filled($change['key'] ?? null) && ($kind = $this->tableKind($change['table'] ?? ''))) {
                    $ids[$kind][] = $change['key'];
                }
            }
        }

        foreach ($ids as $kind => $list) {
            $list = array_values(array_unique(array_map('intval', $list)));
            $this->names[$kind] = ($this->names[$kind] ?? []) + $this->fetch($kind, $list);
        }

        return $this;
    }

    public function tableLabel(string $table): string
    {
        return self::TABLES[$table] ?? $table;
    }

    public function isInternal(array $change): bool
    {
        return in_array($change['table'] ?? '', self::INTERNAL_TABLES, true);
    }

    /**
     * @return array{label: string, icon: string, tone: string, undo: string}
     */
    public function opInfo(string $op): array
    {
        return match ($op) {
            'created' => ['label' => 'إضافة', 'icon' => 'add_circle', 'tone' => 'emerald', 'undo' => 'سيُحذف هذا السجل'],
            'deleted' => ['label' => 'حذف', 'icon' => 'delete', 'tone' => 'rose', 'undo' => 'سيُعاد هذا السجل كما كان'],
            'pivot' => ['label' => 'تعديل صلاحيات', 'icon' => 'key', 'tone' => 'amber', 'undo' => 'ستعود الصلاحيات كما كانت'],
            default => ['label' => 'تعديل', 'icon' => 'edit', 'tone' => 'amber', 'undo' => 'ستعود الحقول المعدّلة إلى قيمها السابقة'],
        };
    }

    /**
     * @return array{label: string, icon: string, tone: string}
     */
    public function operationKind(ApiOperation $operation): array
    {
        $label = $operation->label();

        return match (true) {
            str_starts_with($label, 'إلغاء') => ['label' => 'إلغاء', 'icon' => 'block', 'tone' => 'rose'],
            str_starts_with($label, 'تعديل') => ['label' => 'تعديل', 'icon' => 'edit_note', 'tone' => 'amber'],
            default => ['label' => 'إضافة', 'icon' => 'add_circle', 'tone' => 'emerald'],
        };
    }

    /**
     * The row as it stands after the operation (or before it, for a delete).
     */
    public function row(array $change): array
    {
        $row = ($change['op'] ?? '') === 'deleted' ? ($change['before'] ?? []) : ($change['after'] ?? $change['before'] ?? []);

        return is_array($row) ? $row : [];
    }

    /**
     * A short name for the record: its own name, the party it belongs to, or its number.
     */
    public function recordTitle(array $change): string
    {
        if (($change['op'] ?? '') === 'pivot') {
            return $this->tableLabel($change['table']).' #'.($change['owner_id'] ?? '');
        }

        $row = $this->row($change);
        $kind = $this->tableKind($change['table'] ?? '');
        $own = $kind ? ($this->names[$kind][(int) ($change['key'] ?? 0)] ?? null) : null;
        $title = $row['name'] ?? $own ?? $row['title'] ?? $row['description'] ?? $row['payer_name'] ?? null;

        if (! filled($title)) {
            $title = $this->partyName($row);
        }

        return filled($title) ? Str::limit((string) $title, 60) : '#'.($change['key'] ?? '—');
    }

    public function partyName(array $row): ?string
    {
        if (filled($row['party_id'] ?? null) && ($kind = $this->partyKind($row['party_type'] ?? null))) {
            return $this->names[$kind][(int) $row['party_id']] ?? null;
        }
        foreach (['client_id' => 'client', 'family_member_id' => 'person', 'vendor_id' => 'vendor'] as $column => $kind) {
            if (filled($row[$column] ?? null)) {
                return $this->names[$kind][(int) $row[$column]] ?? null;
            }
        }

        return null;
    }

    /**
     * The record's amount, formatted in its own currency, if it has one.
     */
    public function amount(array $change): ?string
    {
        $row = $this->row($change);

        return isset($row['amount']) && $row['amount'] !== '' ? $this->formatValue('amount', $row['amount'], $row) : null;
    }

    /**
     * The main change of an operation (the record the user actually acted on).
     */
    public function primary(ApiOperation $operation): ?array
    {
        $changes = collect($operation->changes ?? [])->reject(fn ($c) => $this->isInternal($c));

        return $changes->firstWhere('op', 'created') ?? $changes->first() ?? ($operation->changes[0] ?? null);
    }

    /**
     * @return list<array{key: string, label: string, before: ?string, after: ?string, technical: bool}>
     */
    public function fields(array $change): array
    {
        if (($change['op'] ?? '') === 'pivot') {
            return [];
        }

        $before = is_array($change['before'] ?? null) ? $change['before'] : [];
        $after = is_array($change['after'] ?? null) ? $change['after'] : [];
        $op = $change['op'] ?? 'updated';
        $rows = [];

        foreach (array_keys($before + $after) as $key) {
            if (in_array($key, self::SECRET, true)) {
                continue;
            }
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;
            if ($op === 'updated' && (string) $b === (string) $a) {
                continue;
            }
            if ($op !== 'updated' && ($op === 'deleted' ? $b : $a) === null) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'label' => self::FIELDS[$key] ?? (string) $key,
                'before' => $op === 'created' ? null : $this->formatValue($key, $b, $before),
                'after' => $op === 'deleted' ? null : $this->formatValue($key, $a, $after),
                'technical' => in_array($key, self::TECHNICAL, true) || ! isset(self::FIELDS[$key]),
            ];
        }

        // Known fields first, in the order the dictionary lists them.
        $order = array_flip(array_keys(self::FIELDS));
        usort($rows, fn ($x, $y) => [$x['technical'], $order[$x['key']] ?? 999] <=> [$y['technical'], $order[$y['key']] ?? 999]);

        return $rows;
    }

    /**
     * @return array{added: list<int>, removed: list<int>}
     */
    public function pivotDiff(array $change): array
    {
        $before = array_map('intval', (array) ($change['before'] ?? []));
        $after = array_map('intval', (array) ($change['after'] ?? []));

        return [
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ];
    }

    public function formatValue(string $key, mixed $value, array $row = []): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_array($value)) {
            return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE), 120);
        }
        if (in_array($key, self::BOOLEANS, true)) {
            return $value ? 'نعم' : 'لا';
        }
        if (in_array($key, self::AMOUNTS, true) && is_numeric($value)) {
            $currency = $this->names['currency'][(int) ($row['currency_id'] ?? 0)] ?? null;

            return $currency instanceof Currency ? $currency->format($value) : number_format((float) $value, 2);
        }
        if ($key === 'party_id') {
            $kind = $this->partyKind($row['party_type'] ?? null);

            return ($kind ? ($this->names[$kind][(int) $value] ?? null) : null) ?? '#'.$value;
        }
        if (isset(self::LOOKUPS[$key])) {
            $name = $this->names[self::LOOKUPS[$key]][(int) $value] ?? null;

            return $name instanceof Currency ? $name->name : ($name ?? '#'.$value);
        }
        if ($key === 'party_type') {
            return match ($this->partyKind($value)) {
                'client' => 'زبون', 'person' => 'شخص', 'vendor' => 'موظف/مورد', default => (string) $value,
            };
        }
        if ($key === 'direction') {
            return match ((string) $value) {
                'incoming' => 'واردة (قبض)', 'outgoing' => 'صادرة (صرف)',
                'owed_to_us' => 'عليه', 'owed_by_us' => 'له', default => (string) $value,
            };
        }
        if ($key === 'status') {
            return \App\Enums\ClientServiceStatus::tryFrom((string) $value)?->label() ?? (string) $value;
        }
        if ($key === 'reason') {
            return \App\Enums\BalanceMemoReason::tryFrom((string) $value)?->label() ?? Str::limit((string) $value, 120);
        }
        if ($key === 'category') {
            return \App\Enums\GoodsTakeCategory::tryFrom((string) $value)?->label() ?? (string) $value;
        }
        if ($key === 'type') {
            return \App\Enums\VendorType::tryFrom((string) $value)?->label() ?? (string) $value;
        }
        if (str_ends_with($key, '_date') || in_array($key, ['taken_on', 'occurred_on', 'reversed_at'], true)) {
            return rescue(fn () => format_date($value, $key === 'reversed_at'), (string) $value, false);
        }

        return Str::limit((string) $value, 120);
    }

    protected function partyKind(mixed $type): ?string
    {
        return match ((string) $type) {
            'client', Client::class => 'client',
            'person', Person::class => 'person',
            'vendor', Vendor::class => 'vendor',
            default => null,
        };
    }

    protected function tableKind(string $table): ?string
    {
        return match ($table) {
            'clients' => 'client', 'family_members' => 'person', 'vendors' => 'vendor',
            'service_types' => 'service_type', 'users' => 'user', 'roles' => 'role',
            default => null,
        };
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, mixed>
     */
    protected function fetch(string $kind, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return rescue(function () use ($kind, $ids) {
            if ($kind === 'currency') {
                return Currency::query()->whereKey($ids)->get()->keyBy('id')->all();
            }

            $query = match ($kind) {
                'client' => Client::query()->withoutGlobalScope(SoftDeletingScope::class),
                'person' => Person::query()->withoutGlobalScope(SoftDeletingScope::class),
                'vendor' => Vendor::query()->withoutGlobalScope(SoftDeletingScope::class),
                'service_type' => ServiceType::query(),
                'user' => User::query()->where('tenant_id', auth()->user()?->tenant_id),
                'role' => Role::query(),
                'payment_method' => PaymentMethod::query(),
                'fund' => Fund::query(),
            };

            return $query->whereKey($ids)->pluck('name', 'id')->all();
        }, [], false);
    }
}
