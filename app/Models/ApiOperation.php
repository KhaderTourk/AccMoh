<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One write request made through the token API, with a before/after snapshot of every row it touched.
 */
class ApiOperation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'token_name',
        'method',
        'path',
        'route_name',
        'subject_type',
        'subject_id',
        'status_code',
        'request',
        'changes',
        'ip_address',
        'undone_at',
        'undone_by',
    ];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'changes' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function undoer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('undone_at');
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }

    public function label(): string
    {
        $name = (string) $this->route_name;
        $action = match (true) {
            str_ends_with($name, '.reverse') => 'إلغاء',
            str_ends_with($name, '.update') || $this->method === 'PUT' || $this->method === 'PATCH' => 'تعديل',
            default => 'إضافة',
        };

        return $action.' '.$this->sectionLabel();
    }

    public function sectionLabel(): string
    {
        $name = $this->route_name ?: $this->path;
        foreach ([
            'client-services' => 'خدمة زبون',
            'client-goods-takes' => 'منتج مأخوذ',
            'clients.import' => 'استيراد زبائن',
            'clients' => 'زبون',
            'service-types' => 'نوع خدمة',
            'vendor-charges' => 'مستحق',
            'workers' => 'موظف',
            'suppliers' => 'مورد',
            'persons' => 'شخص',
            'payments' => 'دفعة',
            'transfers' => 'تحويل',
            'balance-memos.transfer' => 'ترحيل رصيد بين حسابين',
            'balance-memos' => 'رصيد مرحّل',
            'balances.opening' => 'رصيد افتتاحي',
            'users' => 'مستخدم',
            'roles' => 'دور',
            'cash-payments' => 'دفعة (تطبيق الموبايل)',
            'sync' => 'مزامنة (تطبيق الموبايل)',
        ] as $key => $label) {
            if (str_contains($name, $key)) {
                return $label;
            }
        }

        return $this->path;
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    public function changeCounts(): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        foreach ($this->changes ?? [] as $change) {
            $op = $change['op'] === 'pivot' ? 'updated' : $change['op'];
            $counts[$op] = ($counts[$op] ?? 0) + 1;
        }

        return $counts;
    }
}
