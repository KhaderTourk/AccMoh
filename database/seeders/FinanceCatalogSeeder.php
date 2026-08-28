<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class FinanceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $ils = Currency::query()->firstOrCreate(
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

        Fund::query()->firstOrCreate(['slug' => 'family'], ['name' => 'شخصي']);
        Fund::query()->firstOrCreate(['slug' => 'business'], ['name' => 'العمل']);

        foreach ([
            ['name' => 'إعلان ممول', 'default_price' => 300],
            ['name' => 'إدارة حملة إعلانية', 'default_price' => 500],
            ['name' => 'تصميم إعلان', 'default_price' => 100],
            ['name' => 'إدارة صفحات', 'default_price' => 200],
            ['name' => 'استشارة', 'default_price' => 80],
        ] as $i => $type) {
            ServiceType::query()->firstOrCreate(
                ['name' => $type['name']],
                [
                    'default_price' => $type['default_price'],
                    'default_currency_id' => $ils->id,
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            ['name' => 'مصروف يومي', 'fund_slug' => 'family', 'sort_order' => 1],
            ['name' => 'فواتير', 'fund_slug' => 'family', 'sort_order' => 2],
            ['name' => 'مشتريات شخصية', 'fund_slug' => 'family', 'sort_order' => 3],
            ['name' => 'تكلفة إعلان', 'fund_slug' => 'business', 'sort_order' => 10],
            ['name' => 'اشتراك برنامج', 'fund_slug' => 'business', 'sort_order' => 11],
            ['name' => 'استضافة', 'fund_slug' => 'business', 'sort_order' => 12],
            ['name' => 'أدوات عمل', 'fund_slug' => 'business', 'sort_order' => 13],
            ['name' => 'أخرى', 'fund_slug' => null, 'sort_order' => 99],
        ] as $category) {
            ExpenseCategory::query()->firstOrCreate(
                ['name' => $category['name'], 'fund_slug' => $category['fund_slug']],
                ['is_active' => true, 'sort_order' => $category['sort_order']]
            );
        }
    }
}
