<?php

namespace App\Services\Tenancy;

use App\Enums\FundSlug;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * @param  array{name:string,slug?:string,business_enabled?:bool,notes?:string,owner_name:string,owner_email:string,owner_password:string}  $data
     */
    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            $slug = $data['slug'] ?? Str::slug($data['name']);
            $slug = $this->uniqueSlug($slug);

            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'business_enabled' => (bool) ($data['business_enabled'] ?? true),
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            $adminRole = Role::query()->where('slug', 'admin')->first();

            $owner = User::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make($data['owner_password']),
                'role_id' => $adminRole?->id,
                'tenant_id' => $tenant->id,
                'is_super_admin' => true,
                'is_platform_admin' => false,
                'is_active' => true,
            ]);

            $tenant->update(['owner_user_id' => $owner->id]);

            $this->seedCatalog($tenant);

            return $tenant->fresh(['owner']);
        });
    }

    public function seedCatalog(Tenant $tenant): void
    {
        TenantContext::bypass(true);

        try {
            // Global catalogs (idempotent)
            Currency::query()->updateOrCreate(
                ['code' => 'ILS'],
                ['name' => 'شيكل', 'symbol' => '₪', 'decimal_places' => 2, 'is_active' => true, 'sort_order' => 1]
            );
            Currency::query()->updateOrCreate(
                ['code' => 'USD'],
                ['name' => 'دولار أمريكي', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'sort_order' => 2]
            );
            Currency::query()->updateOrCreate(
                ['code' => 'JOD'],
                ['name' => 'دينار أردني', 'symbol' => 'د.أ', 'decimal_places' => 2, 'is_active' => true, 'sort_order' => 3]
            );

            foreach ([
                ['name' => 'نقدي', 'slug' => 'cash', 'icon' => 'payments', 'sort_order' => 1],
                ['name' => 'بنك فلسطين', 'slug' => 'bank', 'icon' => 'account_balance', 'sort_order' => 2],
                ['name' => 'جوال باي', 'slug' => 'jawwal_pay', 'icon' => 'smartphone', 'sort_order' => 3],
                ['name' => 'بال باي', 'slug' => 'palpay', 'icon' => 'wallet', 'sort_order' => 4],
            ] as $method) {
                PaymentMethod::query()->updateOrCreate(['slug' => $method['slug']], $method + ['is_active' => true]);
            }

            Fund::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => FundSlug::Family->value],
                ['name' => 'شخصي']
            );

            if ($tenant->business_enabled) {
                Fund::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => FundSlug::Business->value],
                    ['name' => 'عمل']
                );
            }

            TenantContext::set($tenant->id);
            TenantContext::bypass(false);

            if ($tenant->business_enabled) {
                foreach ([
                    ['name' => 'إعلان ممول', 'description' => 'إعلان ممول على المنصات'],
                    ['name' => 'إدارة حملة إعلانية', 'description' => null],
                    ['name' => 'تصميم إعلان', 'description' => null],
                    ['name' => 'إدارة صفحات', 'description' => null],
                    ['name' => 'استشارة', 'description' => null],
                ] as $type) {
                    ServiceType::query()->firstOrCreate(
                        ['name' => $type['name']],
                        [
                            'description' => $type['description'],
                            'is_active' => true,
                        ]
                    );
                }
            }
        } finally {
            TenantContext::clear();
            TenantContext::bypass(false);
        }
    }

    public function ensureBusinessFund(Tenant $tenant): void
    {
        if (! $tenant->business_enabled) {
            return;
        }

        Fund::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => FundSlug::Business->value],
            ['name' => 'عمل']
        );
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base ?: 'tenant';
        $i = 1;
        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
