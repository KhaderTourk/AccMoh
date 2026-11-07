<?php

namespace Tests\Feature\Finance;

use App\Models\AdCampaign;
use App\Models\AdMonth;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\VendorCharge;
use App\Services\Finance\AdsService;
use App\Services\Finance\BalanceService;
use App\Support\Rates;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdsTest extends TestCase
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

    protected function ctx(): void
    {
        TenantContext::set((int) $this->user->tenant_id);
    }

    public function test_campaigns_invoices_approval_and_reopen(): void
    {
        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $fund = Fund::business();
        $usd = Currency::byCode('USD');
        $ils = Currency::byCode('ILS');
        $client = Client::query()->create(['name' => 'زبون الإعلانات', 'is_active' => true]);

        // رصيد شيكل بالدرج عشان الفاتورة
        $this->actingAs($this->user)->post(route('cp.balances.settle'), [
            'fund_id' => $fund->id, 'payment_method_id' => $cash->id, 'currency_id' => $ils->id, 'actual' => 5000, 'notes' => 'افتتاحي',
        ])->assertRedirect();

        $this->actingAs($this->user)->get(route('cp.ads.index'))->assertOk()->assertSee('الإعلانات الممولة');

        // حملة لزبون موجود، وحملة لزبون جديد من نفس الشاشة
        $this->actingAs($this->user)->post(route('cp.ads.campaigns.store'), [
            'client_id' => $client->id, 'period' => '2026-10', 'on_facebook' => 1, 'on_instagram' => 1, 'spend_usd' => 100, 'sell_rate' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('cp.ads.campaigns.store'), [
            'new_client' => 'زبون جديد للإعلان', 'period' => '2026-10', 'on_facebook' => 1, 'spend_usd' => 50, 'sell_rate' => 4.5,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->ctx();
        $this->assertSame(1, Client::query()->where('name', 'زبون جديد للإعلان')->count());
        $this->assertSame(5.0, app(AdsService::class)->lastSellRate($client->id));

        // فاتورة ميتا 120 $ بسعر 3.7 من الشيكل
        $this->actingAs($this->user)->post(route('cp.ads.invoices.store'), [
            'invoice_date' => '2026-10-20', 'amount_usd' => 120, 'fund_id' => $fund->id, 'payment_method_id' => $cash->id,
            'paid_in' => 'ILS', 'usd_rate' => 3.7, 'meta_ref' => 'FB-1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->ctx();
        $bal = app(BalanceService::class);
        $this->assertSame('4556.00', $bal->cash($fund->id, $cash->id, $ils->id)); // 5000 − 444
        $this->assertSame('0.00', $bal->cash($fund->id, $cash->id, $usd->id));

        // المطابقة: 0 + 150 − 120 = 30
        $rec = app(AdsService::class)->reconciliation()['2026-10'];
        $this->assertSame(30.0, $rec['expected']);

        // فرق كبير مرفوض، فرق بسيط بدو تخطّي
        $this->actingAs($this->user)->post(route('cp.ads.approve', '2026-10'), ['meta_balance' => 40])->assertSessionHasErrors('ads');
        $this->actingAs($this->user)->post(route('cp.ads.approve', '2026-10'), ['meta_balance' => 31])->assertSessionHasErrors('ads');
        $this->actingAs($this->user)->post(route('cp.ads.approve', '2026-10'), ['meta_balance' => 31, 'skip' => 1])->assertSessionHasNoErrors();
        $this->ctx();
        $this->assertTrue(AdMonth::query()->where('period', '2026-10')->first()->isApproved());
        $this->assertSame(2, ClientService::query()->where('title', 'like', 'إعلان ممول%')->count());
        $this->assertSame('500.00', (string) ClientService::query()->where('client_id', $client->id)->value('amount'));
        $meta = app(AdsService::class)->metaVendor();
        // على ميتا: 150 حملات + 1 تسوية − 120 فاتورة = 31
        $this->assertEqualsWithDelta(31.0, (float) $meta->outstandingAmount($usd->id), 0.001);

        // الربح: 725 مبيع − 150×3.7 = 170 (أول 120 بالفاتورة والباقي 30 تقديري بنفس السعر)
        $p = app(AdsService::class)->profit()['2026-10'];
        $this->assertSame(725.0, $p['billed']);
        $this->assertSame(170.0, $p['profit']);
        $this->assertFalse($p['confirmed']);

        // تعديل حملة بشهر معتمد ← قيد عكسي والشهر يرجع مسودة
        $camp = AdCampaign::query()->where('client_id', $client->id)->first();
        $this->actingAs($this->user)->put(route('cp.ads.campaigns.update', $camp), [
            'client_id' => $client->id, 'period' => '2026-10', 'on_facebook' => 1, 'spend_usd' => 110, 'sell_rate' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->ctx();
        $this->assertFalse(AdMonth::query()->where('period', '2026-10')->first()->isApproved());
        $this->assertSame(0, ClientService::query()->where('title', 'like', 'إعلان ممول%')->count());
        $this->assertSame(0, VendorCharge::query()->where('vendor_id', $meta->id)->count());
        $this->assertEqualsWithDelta(-120.0, (float) $meta->fresh()->outstandingAmount($usd->id), 0.001);

        foreach (['campaigns', 'invoices', 'match'] as $tab) {
            $this->actingAs($this->user)->get(route('cp.ads.index', ['tab' => $tab]))->assertOk();
        }
    }
}
