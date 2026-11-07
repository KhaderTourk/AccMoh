<?php

namespace Tests\Feature\Finance;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientSubscription;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Finance\ClientWorkService;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertMonthlyServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_services_become_subscriptions_without_touching_balances(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Fund::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $user->tenant_id]);
        TenantContext::set((int) $user->tenant_id);
        $ils = Currency::byCode('ILS');
        $work = app(ClientWorkService::class);

        $hosting = ServiceType::query()->create(['name' => 'استضافة', 'billing' => 'monthly', 'is_active' => true]);
        $ads = ServiceType::query()->create(['name' => 'إدارة الإعلانات الممولة', 'billing' => 'monthly', 'is_active' => true]);
        $active = Client::query()->create(['name' => 'زبون شغّال', 'is_active' => true]);
        $old = Client::query()->create(['name' => 'زبون وقف', 'is_active' => true]);
        foreach (['2026-08-03', '2026-09-03', '2026-10-03'] as $d) {
            $work->create(['client_id' => $active->id, 'service_type_id' => $hosting->id, 'title' => 'استضافة', 'amount' => 800, 'currency_id' => $ils->id, 'service_date' => $d]);
        }
        $work->create(['client_id' => $old->id, 'service_type_id' => $hosting->id, 'title' => 'استضافة', 'amount' => 500, 'currency_id' => $ils->id, 'service_date' => '2026-03-01']);
        $adsSub = ClientSubscription::query()->create(['client_id' => $active->id, 'service_type_id' => $ads->id, 'title' => 'إعلانات', 'amount' => 300, 'currency_id' => $ils->id, 'starts_on' => '2026-09-01']);
        $before = [$active->outstandingAmount($ils->id), $old->outstandingAmount($ils->id), ClientService::query()->count()];

        // التجربة ما بتحفظ إشي
        $this->artisan('accounts:convert-monthly-services', ['--today' => '2026-11-07'])->assertSuccessful();
        $this->assertSame(1, ClientSubscription::query()->count());

        $this->artisan('accounts:convert-monthly-services', ['--today' => '2026-11-07', '--apply' => true])->assertSuccessful();
        $sub = ClientSubscription::query()->where('service_type_id', $hosting->id)->sole();
        $this->assertSame($active->id, $sub->client_id);
        $this->assertSame('800.00', (string) $sub->amount);
        $this->assertSame('2026-11-01', $sub->starts_on->toDateString()); // بعد آخر شهر مسجّل
        $this->assertFalse($ads->fresh()->is_active);
        $this->assertSame('2026-10-31', $adsSub->fresh()->ends_on->toDateString());
        $this->assertSame($before, [$active->fresh()->outstandingAmount($ils->id), $old->fresh()->outstandingAmount($ils->id), ClientService::query()->count()]);

        // مرة ثانية ما بتكرر
        $this->artisan('accounts:convert-monthly-services', ['--today' => '2026-11-07', '--apply' => true])->assertSuccessful();
        $this->assertSame(1, ClientSubscription::query()->where('service_type_id', $hosting->id)->count());
        TenantContext::clear();
    }
}
