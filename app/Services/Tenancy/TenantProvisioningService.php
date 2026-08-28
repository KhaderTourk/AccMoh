<?php

namespace App\Services\Tenancy;

use App\Enums\FundSlug;
use App\Models\Currency;
use App\Models\ExpenseCategory;
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
            Currency::query()->firstOrCreate(
                ['code' => 'ILS'],
                ['name' => 'شيكل إسرائيلي', 'symbol' => '₪', 'decimal_places' => 2, 'is_active' => true, 'sort_order' => 1]
            );
            Currency::query()->firstOrCreate(
                ['code' => 'USD'],
                ['name' => 'دولار أمريكي', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'sort_order' => 2]
            );
            Currency::query()->firstOrCreate(
                ['code' => 'JOD'],
                ['name' => 'دينار', 'symbol' => 'د.أ', 'decimal_places' => 2, 'is_active' => true, 'sort_order' => 3]
            );

            foreach ([
                ['name' => 'نقدي', 'slug' => 'cash', 'icon' => 'payments', 'sort_order' => 1],
                ['name' => 'بنكي', 'slug' => 'bank', 'icon' => 'account_balance', 'sort_order' => 2],
                ['name' => 'جوال باي', 'slug' => 'jawwal_pay', 'icon' => 'smartphone', 'sort_order' => 3],
                ['name' => 'بال باي', 'slug' => 'palpay', 'icon' => 'wallet', 'sort_order' => 4],
            ] as $method) {
                PaymentMethod::query()->firstOrCreate(['slug' => $method['slug']], $method + ['is_active' => true]);
            }

            Fund::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => FundSlug::Family->value],
                ['name' => 'شخصي']
            );

            if ($tenant->business_enabled) {
                Fund::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => FundSlug::Business->value],
                    ['name' => 'العمل']
                );
            }

            $ils = Currency::query()->where('code', 'ILS')->first();

            TenantContext::set($tenant->id);
            TenantContext::bypass(false);

            foreach ([
                ['name' => 'مصروف يومي', 'fund_slug' => 'family', 'sort_order' => 1],
                ['name' => 'فواتير', 'fund_slug' => 'family', 'sort_order' => 2],
                ['name' => 'مشتريات شخصية', 'fund_slug' => 'family', 'sort_order' => 3],
                ['name' => 'أخرى', 'fund_slug' => null, 'sort_order' => 99],
            ] as $category) {
                ExpenseCategory::query()->firstOrCreate(
                    ['name' => $category['name'], 'fund_slug' => $category['fund_slug']],
                    ['is_active' => true, 'sort_order' => $category['sort_order']]
                );
            }

            if ($tenant->business_enabled) {
                foreach ([
                    ['name' => 'تكلفة إعلان', 'fund_slug' => 'business', 'sort_order' => 10],
                    ['name' => 'اشتراك برنامج', 'fund_slug' => 'business', 'sort_order' => 11],
                    ['name' => 'استضافة', 'fund_slug' => 'business', 'sort_order' => 12],
                    ['name' => 'أدوات عمل', 'fund_slug' => 'business', 'sort_order' => 13],
                    ['name' => 'أجور عمال', 'fund_slug' => 'business', 'sort_order' => 14],
                    ['name' => 'مشتريات موردين', 'fund_slug' => 'business', 'sort_order' => 15],
                ] as $category) {
                    ExpenseCategory::query()->firstOrCreate(
                        ['name' => $category['name'], 'fund_slug' => $category['fund_slug']],
                        ['is_active' => true, 'sort_order' => $category['sort_order']]
                    );
                }

                foreach ([
                    ['name' => 'إعلان ممول', 'default_price' => 300],
                    ['name' => 'إدارة حملة إعلانية', 'default_price' => 500],
                    ['name' => 'تصميم إعلان', 'default_price' => 100],
                    ['name' => 'إدارة صفحات', 'default_price' => 200],
                    ['name' => 'استشارة', 'default_price' => 80],
                ] as $type) {
                    ServiceType::query()->firstOrCreate(
                        ['name' => $type['name']],
                        [
                            'default_price' => $type['default_price'],
                            'default_currency_id' => $ils?->id,
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
            ['name' => 'العمل']
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
