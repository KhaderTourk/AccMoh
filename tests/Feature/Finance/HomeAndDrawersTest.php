<?php

namespace Tests\Feature\Finance;

use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\PartyBalanceMemo;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\BalanceService;
use App\Support\Rates;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeAndDrawersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $this->user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Fund::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        TenantContext::set((int) $this->user->tenant_id);
        Rates::forget();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        Rates::forget();
        parent::tearDown();
    }

    public function test_home_cards_rates_settle_and_drawer_page(): void
    {
        $ils = Currency::byCode('ILS');
        $usd = Currency::byCode('USD');
        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $fund = Fund::business();

        // سعر اليوم من الشاشة
        $this->actingAs($this->user)->post(route('cp.balances.rates'), ['rates' => ['USD' => '3.5']])->assertRedirect();
        $this->assertSame('3.5', AppSetting::get('rate.USD'));
        Rates::forget();

        // تسوية: الموجود 100 $ والنظام صفر ← حركة بالفرق، والملاحظة إجبارية
        $this->actingAs($this->user)->post(route('cp.balances.settle'), [
            'fund_id' => $fund->id, 'payment_method_id' => $cash->id, 'currency_id' => $usd->id, 'actual' => 100,
        ])->assertSessionHasErrors('notes');
        $this->actingAs($this->user)->post(route('cp.balances.settle'), [
            'fund_id' => $fund->id, 'payment_method_id' => $cash->id, 'currency_id' => $usd->id, 'actual' => 100, 'notes' => 'عدّيت الدرج',
        ])->assertRedirect();
        $this->assertSame('100.00', app(BalanceService::class)->cash($fund->id, $cash->id, $usd->id));
        $this->assertSame(1, LedgerEntry::query()->where('description', 'تسوية رصيد')->count());

        TenantContext::set((int) $this->user->tenant_id); // الطلبات اللي قبل بتمسح السياق
        $client = Client::query()->create(['name' => 'زبون الرئيسية', 'is_active' => true]);
        PartyBalanceMemo::query()->create(['party_type' => $client->getMorphClass(), 'party_id' => $client->id, 'direction' => 'owed_to_us',
            'reason' => 'opening', 'amount' => 200, 'currency_id' => $ils->id, 'memo_date' => now()->toDateString()]);

        $this->actingAs($this->user)->get(route('cp.dashboard'))->assertOk()
            ->assertSee('350.00 ₪')
            ->assertSee('على الزبائن')->assertSee('200.00 ₪')
            ->assertSee('550.00 ₪')          // الصافي
            ->assertSee('زبون الرئيسية');

        $this->actingAs($this->user)->get(route('cp.balances.index'))->assertOk()->assertSee('المجموع بسعر اليوم')->assertSee('100.00 $');
        $this->actingAs($this->user)->get(route('cp.balances.show', ['method' => $cash->id, 'cur' => 'USD']))->assertOk()
            ->assertSee('تسوية رصيد')->assertSee('عدّيت الدرج');
    }
}
