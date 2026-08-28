<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class FinancialAuditLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'model_type',
        'model_id',
        'payload',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, Model $entity, array $payload = []): self
    {
        $data = [
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->getKey(),
            'payload' => $payload,
        ];

        // Compatibility with legacy audit columns still present in some databases
        if (Schema::hasColumn('financial_audit_logs', 'model_type')) {
            $data['model_type'] = $entity::class;
            $data['model_id'] = $entity->getKey();
        }
        if (Schema::hasColumn('financial_audit_logs', 'ip_address')) {
            $data['ip_address'] = request()?->ip();
        }

        return static::query()->create($data);
    }
}
