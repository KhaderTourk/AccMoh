<?php

namespace Tests\Feature\Finance;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientSubscription;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCharge;
use App\Support\MonthlyPlan;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** الاشتراكات والرواتب الشهرية (قرار 10/11/2026). */
class MonthlyPlansTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Currency $ils;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $this->user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Fund::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        ServiceType::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        TenantContext::set((int) $this->user->tenant_id);
        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_partial_months_use_the_real_month_length_without_rounding(): void
    {
        $d = fn ($s) => CarbonImmutable::parse($s);
        $this->assertSame([20, 30], MonthlyPlan::daysIn($d('2026-11-11'), null, '2026-11'));
        $this->assertSame('300.00', MonthlyPlan::suggested(450, $d('2026-11-11'), null, '2026-11'));
        $this->assertSame([18, 31], MonthlyPlan::daysIn($d('2026-06-01'), $d('2026-12-18'), '2026-12'));
        $this->assertSame('348.39', MonthlyPlan::suggested(600, $d('2026-06-01'), $d('2026-12-18'), '2026-12'));
        $this->assertSame([28, 28], MonthlyPlan::daysIn($d('2026-01-01'), null, '2027-02'));
        $this->assertSame('450.00', MonthlyPlan::suggested(450, $d('2026-01-01'), null, '2027-02'));
    }

    public function test_new_subscription_waits_in_the_monthly_filter_and_is_added_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-12'));
        $client = Client::query()->create(['name' => 'مطعم الريف', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('cp.subscriptions.store'), [
            'client_id' => $client->id, 'title' => 'إدارة صفحات', 'amount' => 450, 'starts_on' => '11/11/2026',
        ])->assertRedirect();
        $sub = ClientSubscription::query()->firstOrFail();
        $this->assertSame(0, ClientService::query()->where('subscription_id', $sub->id)->count());

        $plan = \App\Http\Controllers\Cp\ClientSubscriptionController::plansByClient()->get($client->id)->first();
        $this->assertSame('2026-11', $plan['pending'][0]['period']);
        $this->assertSame(300.0, $plan['pending'][0]['amount']);

        $key = $sub->id.'|2026-11';
        $send = fn () => $this->actingAs($this->user)->post(route('cp.subscriptions.confirm.store'), [
            'items' => [$key => ['on' => 1, 'amount' => 310]], 'month' => '2026-11', 'return' => 'clients',
        ]);
        $send()->assertRedirect(route('cp.clients.index'));
        $send();
        $this->assertSame(1, ClientService::query()->where('subscription_id', $sub->id)->count());
        $this->assertSame('310.00', ClientService::query()->where('subscription_id', $sub->id)->value('amount'));
    }

    public function test_stopping_adds_the_last_month_in_the_same_step(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05'));
        $client = Client::query()->create(['name' => 'عيادة', 'is_active' => true]);
        $sub = ClientSubscription::query()->create([
            'client_id' => $client->id, 'title' => 'تصميم', 'amount' => 600, 'currency_id' => $this->ils->id, 'starts_on' => '2026-10-01',
        ]);
        $this->actingAs($this->user)->post(route('cp.subscriptions.stop', $sub), ['ends_on' => '5/11/2026', 'last_amount' => '100'])
            ->assertRedirect(route('cp.clients.show', $client));
        $this->assertSame('2026-11-05', $sub->fresh()->ends_on->toDateString());
        $this->assertSame('100.00', ClientService::query()->where('subscription_id', $sub->id)->where('period', '2026-11')->value('amount'));
        // October is still waiting to be added by hand.
        $this->assertSame(['2026-10'], $sub->fresh()->pendingMonths('2026-11'));
    }

    public function test_worker_salary_month_is_added_once_with_the_partial_amount(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20'));
        $worker = Vendor::query()->create(['name' => 'ليان', 'type' => 'worker', 'is_active' => true, 'salary_amount' => 2200, 'salary_from' => '2026-11-09']);
        $plan = \App\Http\Controllers\Cp\VendorMonthlyController::plans($worker)[0];
        $this->assertSame(1613.33, $plan['pending'][0]['amount']);

        $send = fn () => $this->actingAs($this->user)->post(route('cp.vendor-monthly.store', $worker), ['period' => '2026-11', 'amount' => '1613.33']);
        $send()->assertRedirect(route('cp.workers.index'))->assertSessionHas('success');
        $send()->assertSessionHas('error');
        $this->assertSame(1, VendorCharge::query()->where('vendor_id', $worker->id)->where('period', '2026-11')->count());
        $this->assertSame([], \App\Http\Controllers\Cp\VendorMonthlyController::plans($worker->fresh())[0]['pending']);
    }

    public function test_supplier_monthly_service_is_saved_from_the_list_window(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20'));
        $this->actingAs($this->user)->post(route('cp.suppliers.store'), [
            'name' => 'مزوّد الإنترنت', 'detail' => 'إنترنت المكتب', 'salary_amount' => 150, 'salary_from' => '1/11/2026', 'return' => 'list',
        ])->assertRedirect();
        $supplier = Vendor::query()->where('name', 'مزوّد الإنترنت')->firstOrFail();
        $this->assertSame(['2026-11'], $supplier->pendingMonths());
        $this->actingAs($this->user)->get(route('cp.suppliers.index'))->assertOk()->assertSee('الخدمات الشهرية');
    }
}
