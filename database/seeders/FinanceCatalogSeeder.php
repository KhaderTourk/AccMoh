<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class FinanceCatalogSeeder extends Seeder
{
    public function run(): void
    {
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

        Fund::query()->updateOrCreate(['slug' => 'family'], ['name' => 'شخصي']);
        Fund::query()->updateOrCreate(['slug' => 'business'], ['name' => 'عمل']);

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
}
