<?php

namespace Tests\Feature\Finance;

use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPageTest extends TestCase
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
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_page_windows_record_service_payment_discount_and_opening_balance(): void
    {
        $client = Client::query()->create(['name' => 'زبون الصفحة', 'company_name' => 'جهة الصفحة', 'is_active' => true]);
        $type = ServiceType::query()->where('is_active', true)->firstOrFail();
        $method = PaymentMethod::query()->where('is_active', true)->firstOrFail();
        $fund = Fund::query()->where('slug', 'business')->first() ?? Fund::family();

        $this->actingAs($this->user)->get(route('cp.clients.show', $client))
            ->assertOk()
            ->assertSee('جهة الصفحة')
            ->assertSee('كشف حساب');

        // Dates come from the window as day/month/year.
        $this->actingAs($this->user)->post(route('cp.client-services.store'), [
            'client_id' => $client->id, 'service_type_id' => $type->id,
            'currency_id' => $this->ils->id, 'amount' => 500, 'service_date' => '3/10/2026',
        ])->assertRedirect(route('cp.clients.show', $client));

        $this->actingAs($this->user)->post(route('cp.payments.store', ['incoming']), [
            'party_key' => 'client:'.$client->id, 'fund_id' => $fund->id, 'payment_method_id' => $method->id,
            'currency_id' => $this->ils->id, 'amount' => 200, 'occurred_on' => '5/10/2026', 'name' => 'جهة الصفحة',
        ])->assertRedirect(route('cp.clients.show', $client));

        $this->actingAs($this->user)->post(route('cp.client-goods-takes.store'), [
            'party_key' => 'client:'.$client->id, 'party_type' => 'client', 'party_id' => $client->id,
            'category' => 'discount', 'currency_id' => $this->ils->id, 'amount' => 50, 'taken_on' => '6/10/2026',
        ])->assertRedirect(route('cp.clients.show', $client));

        $this->actingAs($this->user)->post(route('cp.balance-memos.store'), [
            'party_key' => 'client:'.$client->id, 'party_type' => 'client', 'party_id' => $client->id,
            'reason' => 'opening', 'direction' => 'owed_to_us', 'currency_id' => $this->ils->id,
            'amount' => 100, 'memo_date' => '1/1/2026',
        ])->assertRedirect(route('cp.clients.show', $client));

        $this->assertSame('350.00', $client->fresh()->outstandingAmount($this->ils->id));

        $this->actingAs($this->user)->get(route('cp.clients.show', $client))
            ->assertOk()
            ->assertSee('"group":"svc"', false)
            ->assertSee('"group":"pay"', false)
            ->assertSee('"kind":"خصم"', false)
            ->assertSee('"kind":"رصيد سابق"', false);
    }

    public function test_deleting_a_payment_from_the_client_page_returns_to_it(): void
    {
        $client = Client::query()->create(['name' => 'زبون الحذف', 'is_active' => true]);
        $method = PaymentMethod::query()->where('is_active', true)->firstOrFail();
        $fund = Fund::query()->where('slug', 'business')->first() ?? Fund::family();

        $this->actingAs($this->user)->post(route('cp.payments.store', ['incoming']), [
            'party_key' => 'client:'.$client->id, 'fund_id' => $fund->id, 'payment_method_id' => $method->id,
            'currency_id' => $this->ils->id, 'amount' => 75, 'occurred_on' => '5/10/2026',
        ]);
        $payment = CashPayment::query()->latest('id')->firstOrFail();

        $this->actingAs($this->user)
            ->from(route('cp.clients.show', $client))
            ->delete(route('cp.payments.destroy', $payment))
            ->assertRedirect(route('cp.clients.show', $client));

        $this->assertSame('0.00', $client->fresh()->outstandingAmount($this->ils->id));
    }
}
