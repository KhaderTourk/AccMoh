<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'business_enabled',
        'is_active',
        'owner_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'business_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class);
    }

    public function isBusinessEnabled(): bool
    {
        return (bool) $this->business_enabled;
    }
}
