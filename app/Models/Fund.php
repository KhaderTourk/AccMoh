<?php

namespace App\Models;

use App\Enums\FundSlug;
use App\Models\Concerns\BelongsToTenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug'];

    public function slugEnum(): FundSlug
    {
        return FundSlug::from($this->slug);
    }

    public static function family(): self
    {
        return static::query()->where('slug', FundSlug::Family->value)->firstOrFail();
    }

    public static function business(): self
    {
        return static::query()->where('slug', FundSlug::Business->value)->firstOrFail();
    }

    public static function familyFor(?int $tenantId = null): self
    {
        $tenantId = $tenantId ?? TenantContext::check();

        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('slug', FundSlug::Family->value)
            ->firstOrFail();
    }

    public static function businessFor(?int $tenantId = null): self
    {
        $tenantId = $tenantId ?? TenantContext::check();

        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('slug', FundSlug::Business->value)
            ->firstOrFail();
    }
}
