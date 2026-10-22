<?php

namespace App\Services\Api;

use App\Exceptions\FinanceException;
use App\Models\ApiOperation;
use App\Models\Client;
use App\Models\FinancialAuditLog;
use App\Models\Person;
use App\Models\Role;
use App\Models\ServiceType;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every row a token-API write request creates, updates or deletes,
 * so the operation can be shown in the CP and undone later.
 */
class ApiJournal
{
    /** Models whose rows are never journaled (history must survive an undo). */
    protected const IGNORED = [ApiOperation::class, FinancialAuditLog::class];

    /**
     * Rows that must not be deleted on undo while other records still point at them.
     * class => [relation => human label]
     */
    protected const DEPENDENTS = [
        Client::class => ['services' => 'خدمات', 'cashPayments' => 'دفعات', 'goodsTakes' => 'منتجات مأخوذة', 'balanceMemos' => 'أرصدة مرحّلة'],
        Person::class => ['cashPayments' => 'دفعات', 'goodsTakes' => 'منتجات مأخوذة', 'balanceMemos' => 'أرصدة مرحّلة'],
        Vendor::class => ['charges' => 'مستحقات', 'cashPayments' => 'دفعات', 'goodsTakes' => 'منتجات مأخوذة', 'balanceMemos' => 'أرصدة مرحّلة'],
        ServiceType::class => ['clientServices' => 'خدمات زبائن'],
        Role::class => ['users' => 'مستخدمين'],
    ];

    protected const HIDDEN_INPUT = ['password', 'password_confirmation', 'token'];

    protected bool $active = false;

    /** @var array<string, array<string, mixed>> keyed by "table:key" */
    protected array $entries = [];

    /** @var array<string, array<string, mixed>> */
    protected array $pivots = [];

    public function start(): void
    {
        $this->active = true;
        $this->entries = [];
        $this->pivots = [];
    }

    public function active(): bool
    {
        return $this->active;
    }

    /**
     * Called for eloquent.created / updated / deleted events.
     */
    public function capture(string $event, Model $model): void
    {
        if (! $this->active || in_array($model::class, self::IGNORED, true)) {
            return;
        }

        $id = $model->getTable().':'.$model->getKey();
        $existing = $this->entries[$id] ?? null;

        if ($event === 'created') {
            $this->entries[$id] = $this->entry('created', $model, null);

            return;
        }

        if ($existing) {
            // Already known: keep the earliest "before"; a row created in this request stays "created".
            if ($event === 'deleted' && $existing['op'] === 'created') {
                unset($this->entries[$id]);
            } elseif ($event === 'deleted') {
                $this->entries[$id]['op'] = 'deleted';
            }

            return;
        }

        $this->entries[$id] = $this->entry($event, $model, $model->getRawOriginal());
    }

    /**
     * Snapshot rows that are about to be changed by a mass query update (no model events fire).
     */
    public function watch(Model ...$models): void
    {
        if (! $this->active) {
            return;
        }

        foreach ($models as $model) {
            $id = $model->getTable().':'.$model->getKey();
            $this->entries[$id] ??= $this->entry('updated', $model, $model->getRawOriginal());
        }
    }

    /**
     * Snapshot a pivot list (e.g. role_permission) before it is synced.
     */
    public function watchPivot(string $table, string $ownerColumn, int $ownerId, string $relatedColumn): void
    {
        if (! $this->active) {
            return;
        }

        $id = "{$table}:{$ownerColumn}:{$ownerId}";
        $this->pivots[$id] ??= [
            'op' => 'pivot',
            'table' => $table,
            'owner_column' => $ownerColumn,
            'owner_id' => $ownerId,
            'related_column' => $relatedColumn,
            'before' => $this->pivotIds($table, $ownerColumn, $ownerId, $relatedColumn),
            'after' => null,
        ];
    }

    /**
     * Persist the operation when the request succeeded and changed something.
     */
    public function finish(Request $request, Response $response): ?ApiOperation
    {
        if (! $this->active) {
            return null;
        }
        $this->active = false;

        $changes = $this->finalChanges();
        if ($response->getStatusCode() >= 300 || $changes === []) {
            return null;
        }

        $subject = collect($changes)->first(fn ($c) => $c['op'] === 'created') ?? $changes[0];

        return ApiOperation::query()->create([
            'tenant_id' => $request->user()?->tenant_id,
            'user_id' => $request->user()?->id,
            'token_name' => $request->user()?->currentAccessToken()?->name ?? null,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route_name' => $request->route()?->getName(),
            'subject_type' => $subject['model'] ?? null,
            'subject_id' => isset($subject['key']) && is_numeric($subject['key']) ? (int) $subject['key'] : null,
            'status_code' => $response->getStatusCode(),
            'request' => Arr::except($request->except(self::HIDDEN_INPUT), ['files']),
            'changes' => $changes,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Rows of this operation that changed afterwards, or records that now depend on rows it created.
     *
     * @return list<string>
     */
    public function conflicts(ApiOperation $operation): array
    {
        $problems = [];
        $created = [];
        foreach ($operation->changes as $change) {
            if ($change['op'] === 'created') {
                $created[$change['table']][] = (string) $change['key'];
            }
        }

        foreach ($operation->changes as $change) {
            if ($change['op'] === 'pivot') {
                $now = $this->pivotIds($change['table'], $change['owner_column'], $change['owner_id'], $change['related_column']);
                if ($now !== $change['after']) {
                    $problems[] = 'تغيّرت صلاحيات الدور بعد هذه العملية.';
                }

                continue;
            }

            $current = $this->fetch($change['table'], $change['key_name'], $change['key']);
            $label = $this->describe($change);

            if ($change['op'] === 'deleted') {
                if ($current !== null && $change['after'] === null) {
                    $problems[] = "{$label}: أُعيد إنشاؤه بعد هذه العملية.";
                }

                continue;
            }

            if ($current === null) {
                $problems[] = "{$label}: لم يعد موجوداً.";

                continue;
            }
            if ($this->normalize($current) !== $this->normalize($change['after'])) {
                $problems[] = "{$label}: عُدّل بعد هذه العملية.";
            }

            if ($change['op'] === 'created' && isset(self::DEPENDENTS[$change['model']])) {
                $model = $change['model']::query()->withoutGlobalScopes()->find($change['key']);
                foreach (self::DEPENDENTS[$change['model']] as $relation => $relationLabel) {
                    if (! $model || ! method_exists($model, $relation)) {
                        continue;
                    }
                    $related = $model->{$relation}();
                    $relatedTable = $related->getRelated()->getTable();
                    $count = $related->withoutGlobalScopes()
                        ->whereNotIn($related->getRelated()->getQualifiedKeyName(), $created[$relatedTable] ?? [])
                        ->count();
                    if ($count > 0) {
                        $problems[] = "{$label}: لديه {$count} من {$relationLabel} أضيفت بعد هذه العملية.";
                    }
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * Put every touched row back exactly as it was before the API request.
     */
    public function undo(ApiOperation $operation, int $userId): void
    {
        if ($operation->isUndone()) {
            throw new FinanceException('تم التراجع عن هذه العملية مسبقاً.');
        }

        DB::transaction(function () use ($operation, $userId) {
            $operation = ApiOperation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($operation->isUndone()) {
                throw new FinanceException('تم التراجع عن هذه العملية مسبقاً.');
            }

            $problems = $this->conflicts($operation);
            if ($problems !== []) {
                throw new FinanceException('لا يمكن التراجع: '.implode(' ', $problems).' تراجع عن العمليات الأحدث أولاً.');
            }

            foreach (array_reverse($operation->changes) as $change) {
                if ($change['op'] === 'pivot') {
                    DB::table($change['table'])->where($change['owner_column'], $change['owner_id'])->delete();
                    DB::table($change['table'])->insert(array_map(fn ($id) => [
                        $change['owner_column'] => $change['owner_id'],
                        $change['related_column'] => $id,
                    ], $change['before']));

                    continue;
                }

                $query = DB::table($change['table'])->where($change['key_name'], $change['key']);
                match ($change['op']) {
                    'created' => $query->delete(),
                    'updated' => $query->update(Arr::except($change['before'], [$change['key_name']])),
                    'deleted' => $change['after'] === null
                        ? DB::table($change['table'])->insert($change['before'])
                        : $query->update(Arr::except($change['before'], [$change['key_name']])),
                };
            }

            $operation->update(['undone_at' => now(), 'undone_by' => $userId]);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function finalChanges(): array
    {
        $changes = [];
        foreach ($this->entries as $entry) {
            $after = $this->fetch($entry['table'], $entry['key_name'], $entry['key']);
            if ($entry['op'] === 'created' && $after === null) {
                continue;
            }
            if ($entry['op'] !== 'created' && $after !== null && $this->normalize($after) === $this->normalize($entry['before'])) {
                continue;
            }
            $entry['after'] = $after;
            $changes[] = $entry;
        }

        foreach ($this->pivots as $pivot) {
            $pivot['after'] = $this->pivotIds($pivot['table'], $pivot['owner_column'], $pivot['owner_id'], $pivot['related_column']);
            if ($pivot['after'] !== $pivot['before']) {
                $changes[] = $pivot;
            }
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function entry(string $op, Model $model, ?array $before): array
    {
        return [
            'op' => $op,
            'model' => $model::class,
            'table' => $model->getTable(),
            'key_name' => $model->getKeyName(),
            'key' => $model->getKey(),
            'before' => $before,
            'after' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetch(string $table, string $keyName, mixed $key): ?array
    {
        $row = DB::table($table)->where($keyName, $key)->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<int>
     */
    protected function pivotIds(string $table, string $ownerColumn, int $ownerId, string $relatedColumn): array
    {
        return DB::table($table)->where($ownerColumn, $ownerId)->orderBy($relatedColumn)
            ->pluck($relatedColumn)->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array<string, string|null>
     */
    protected function normalize(?array $row): array
    {
        return collect($row ?? [])
            ->map(fn ($value) => $value === null ? null : (string) $value)
            ->sortKeys()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $change
     */
    protected function describe(array $change): string
    {
        $names = [
            'clients' => 'الزبون', 'client_services' => 'الخدمة', 'client_goods_takes' => 'المنتج المأخوذ',
            'cash_payments' => 'الدفعة', 'ledger_entries' => 'قيد', 'fund_transfers' => 'التحويل',
            'party_balance_memos' => 'الرصيد المرحّل', 'family_members' => 'الشخص', 'vendors' => 'الموظف/المورد',
            'vendor_charges' => 'المستحق', 'service_types' => 'نوع الخدمة', 'users' => 'المستخدم', 'roles' => 'الدور',
        ];

        return ($names[$change['table']] ?? $change['table']).' #'.$change['key'];
    }
}
