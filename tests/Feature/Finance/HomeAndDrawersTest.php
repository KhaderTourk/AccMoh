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

        // كل العملات بكل كرت حتى لو صفر، والأرقام متموّهة (bl-money)، وزر التحويل بقائمته.
        $this->actingAs($this->user)->get(route('cp.balances.index'))->assertOk()->assertSee('المجموع بسعر اليوم')
            ->assertSee('100.00')->assertSee('دينار د.أ')->assertSee('bl-money')->assertSee('data-money-eye', false)->assertDontSee('إظهار الأرقام</span>', false)
            ->assertSee('تحويل بين الأدراج')->assertSee('تحويل عملات');
        $this->actingAs($this->user)->get(route('cp.balances.show', ['method' => $cash->id, 'cur' => 'USD']))->assertOk()
            ->assertSee('تسوية رصيد')->assertSee('عدّيت الدرج');
    }

    public function test_drawer_places_are_display_only_and_capped_by_balance(): void
    {
        $ils = Currency::byCode('ILS');
        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $fund = Fund::business();
        $this->actingAs($this->user)->post(route('cp.balances.settle'), [
            'fund_id' => $fund->id, 'payment_method_id' => $cash->id, 'currency_id' => $ils->id, 'actual' => 5000, 'notes' => 'بداية',
        ])->assertRedirect();
        $before = LedgerEntry::query()->count();

        // أكتر من رصيد الدرج ← مرفوض
        $this->actingAs($this->user)->post(route('cp.balances.places', ['method' => $cash->id]), [
            'places' => [['name' => 'الخزنة', 'amounts' => ['ILS' => 6000]]],
        ])->assertSessionHasErrors('places');

        $this->actingAs($this->user)->post(route('cp.balances.places', ['method' => $cash->id]), [
            'places' => [
                ['name' => 'الخزنة', 'amounts' => ['ILS' => 1000]],
                ['name' => 'المكتب', 'amounts' => ['ILS' => 2000]],
                ['name' => '', 'amounts' => []],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        TenantContext::set((int) $this->user->tenant_id);
        $this->assertCount(2, \App\Support\DrawerPlaces::get($cash->id));
        $this->assertSame($before, LedgerEntry::query()->count()); // ما في ولا حركة
        $this->assertSame('5000.00', app(BalanceService::class)->drawerCash($cash->id, $ils->id));

        $this->actingAs($this->user)->get(route('cp.balances.show', ['method' => $cash->id]))->assertOk()
            ->assertSee('الأماكن')->assertSee('الخزنة')->assertSee('المكتب')->assertSee('غير موزّع')->assertSee('2,000.00');
    }

    public function test_transfer_wizard_moves_and_exchanges_and_stays_on_page(): void
    {
        $ils = Currency::byCode('ILS');
        $usd = Currency::byCode('USD');
        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $other = PaymentMethod::query()->where('id', '!=', $cash->id)->firstOrFail();
        $fund = Fund::business();
        $this->actingAs($this->user)->post(route('cp.balances.settle'), [
            'fund_id' => $fund->id, 'payment_method_id' => $cash->id, 'currency_id' => $usd->id, 'actual' => 500, 'notes' => 'بداية',
        ])->assertRedirect();
        $back = route('cp.balances.index');

        // بين درجين بعملتين: 20 $ بيوصلوا 74 ₪
        $this->actingAs($this->user)->from($back)->post(route('cp.transfers.store'), [
            'stay' => 1, 'from_payment_method_id' => $cash->id, 'to_payment_method_id' => $other->id,
            'currency_id' => $usd->id, 'to_currency_id' => $ils->id, 'amount' => '20.00', 'to_amount' => '74.00', 'transfer_date' => now()->toDateString(),
        ])->assertRedirect($back)->assertSessionHas('success');

        // صرف داخل نفس الدرج: 100 $ ← 370 ₪
        $this->actingAs($this->user)->from($back)->post(route('cp.transfers.store'), [
            'stay' => 1, 'from_payment_method_id' => $cash->id, 'to_payment_method_id' => $cash->id,
            'currency_id' => $usd->id, 'to_currency_id' => $ils->id, 'amount' => '100.00', 'to_amount' => '370.00', 'transfer_date' => now()->toDateString(),
        ])->assertRedirect($back)->assertSessionHas('success', 'تم الصرف.');

        TenantContext::set((int) $this->user->tenant_id);
        $b = app(BalanceService::class);
        $this->assertSame('380.00', $b->drawerCash($cash->id, $usd->id));
        $this->assertSame('370.00', $b->drawerCash($cash->id, $ils->id));
        $this->assertSame('74.00', $b->drawerCash($other->id, $ils->id));
    }
}
