<?php

namespace Tests\Feature\Finance;

use App\Enums\PaymentDirection;
use App\Enums\VendorType;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\ClientGoodsTake;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Finance\CashPaymentService;
use App\Services\Finance\ClientGoodsTakeService;
use App\Services\Finance\ClientWorkService;
use App\Services\Finance\VendorChargeService;
use App\Support\Money;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientGoodsTakeTest extends TestCase
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
        TenantContext::set((int) $this->user->tenant_id);
        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_goods_take_reduces_outstanding_without_cash_movement(): void
    {
        $client = Client::query()->create(['name' => 'مخبز السعادة', 'is_active' => true]);
        app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'إعلان ممول',
            'amount' => 800,
            'currency_id' => $this->ils->id,
            'service_date' => '2026-09-01',
        ]);

        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        app(CashPaymentService::class)->record([
            'direction' => PaymentDirection::Incoming,
            'party_type' => 'client',
            'party_id' => $client->id,
            'amount' => 100,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $cash->id,
            'occurred_on' => '2026-09-02',
        ]);

        $ledgerBefore = $this->ledgerTotal();
        $this->assertSame('700.00', $client->fresh()->outstandingAmount($this->ils->id));

        app(ClientGoodsTakeService::class)->create([
            'client_id' => $client->id,
            'title' => 'علبة شوكولاتة',
            'amount' => 200,
            'currency_id' => $this->ils->id,
            'taken_on' => '2026-09-03',
        ]);

        $client = $client->fresh();
        $this->assertSame('800.00', $client->billedAmount($this->ils->id));
        $this->assertSame('100.00', $client->paidAmount($this->ils->id));
        $this->assertSame('200.00', $client->goodsTakenAmount($this->ils->id));
        $this->assertSame('500.00', $client->outstandingAmount($this->ils->id));
        $this->assertSame($ledgerBefore, $this->ledgerTotal());
        $this->assertSame(1, CashPayment::query()->incoming()->active()->count());
    }

    public function test_opening_balance_includes_goods_taken_before_period(): void
    {
        $client = Client::query()->create(['name' => 'آكسنت سنتر', 'is_active' => true]);
        app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'إدارة صفحات',
            'amount' => 500,
            'currency_id' => $this->ils->id,
            'service_date' => '2026-08-10',
        ]);
        app(ClientGoodsTakeService::class)->create([
            'client_id' => $client->id,
            'title' => 'منتج أغسطس',
            'amount' => 150,
            'currency_id' => $this->ils->id,
            'taken_on' => '2026-08-20',
        ]);

        $this->assertSame('350.00', $client->fresh()->openingBalance($this->ils->id, '2026-09-01'));
        $this->assertSame('0.00', $client->fresh()->goodsTakenAmount($this->ils->id, '2026-09-01', '2026-09-30'));
    }

    public function test_client_page_can_record_goods_take(): void
    {
        $client = Client::query()->create(['name' => 'زبون المنتجات', 'is_active' => true]);
        app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'إعلان ممول',
            'amount' => 400,
            'currency_id' => $this->ils->id,
            'service_date' => '2026-09-01',
        ]);

        $this->actingAs($this->user)
            ->get(route('cp.clients.show', $client))
            ->assertOk()
            ->assertSee('خصم/أخرى')
            ->assertSee(route('cp.client-goods-takes.create', ['client_id' => $client->id]), false);

        $this->actingAs($this->user)
            ->get(route('cp.client-goods-takes.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('خصم/أخرى')
            ->assertSee('التصنيف')
            ->assertSee('سلعة')
            ->assertSee('خدمة');

        $this->actingAs($this->user)
            ->post(route('cp.client-goods-takes.store'), [
                'client_id' => $client->id,
                'category' => 'other',
                'title' => 'علبة شوكولاتة',
                'amount' => 80,
                'currency_id' => $this->ils->id,
                'taken_on' => '2026-09-05',
                'notes' => 'من المحل',
            ])
            ->assertRedirect(route('cp.clients.show', $client));

        $this->assertDatabaseHas('client_goods_takes', [
            'client_id' => $client->id,
            'title' => 'علبة شوكولاتة',
            'amount' => '80.00',
        ]);
        $this->assertSame('320.00', $client->fresh()->outstandingAmount($this->ils->id));

        $this->actingAs($this->user)
            ->get(route('cp.clients.show', $client))
            ->assertOk()
            ->assertSee('علبة شوكولاتة')
            ->assertSee('بضاعة مأخوذة');

        $this->assertSame(1, ClientGoodsTake::query()->count());
    }

    public function test_preset_category_is_stored_as_the_classification_title(): void
    {
        $client = Client::query()->create(['name' => 'زبون التصنيف', 'is_active' => true]);

        $this->actingAs($this->user)
            ->post(route('cp.client-goods-takes.store'), [
                'client_id' => $client->id,
                'category' => 'discount',
                'amount' => 15,
                'currency_id' => $this->ils->id,
                'taken_on' => '2026-09-06',
            ])
            ->assertRedirect(route('cp.clients.show', $client));

        $this->assertDatabaseHas('client_goods_takes', [
            'client_id' => $client->id,
            'title' => 'خصم',
        ]);
    }

    public function test_create_forms_choose_organization_instead_of_person_name(): void
    {
        $client = Client::query()->create([
            'name' => 'أحمد الشخص',
            'company_name' => 'مؤسسة النور',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('cp.payments.create', ['incoming', 'client_id' => $client->id]))
            ->assertOk()
            ->assertSee('>مؤسسة النور</option>', false)
            ->assertDontSee('أحمد الشخص');

        $this->actingAs($this->user)
            ->get(route('cp.client-services.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('الجهة')
            ->assertSee('>مؤسسة النور</option>', false)
            ->assertDontSee('أحمد الشخص');

        $this->actingAs($this->user)
            ->get(route('cp.client-goods-takes.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('>مؤسسة النور</option>', false)
            ->assertDontSee('أحمد الشخص');
    }

    public function test_goods_take_from_worker_vendor_and_person_defaults_to_other(): void
    {
        $worker = Vendor::query()->create([
            'name' => 'موظف الخصم',
            'type' => VendorType::Worker,
            'is_active' => true,
        ]);
        $person = Person::query()->create(['name' => 'شخص الخصم', 'is_active' => true]);
        app(VendorChargeService::class)->create([
            'vendor_id' => $worker->id,
            'title' => 'راتب',
            'amount' => 500,
            'currency_id' => $this->ils->id,
            'charge_date' => '2026-09-01',
        ]);

        $this->actingAs($this->user)
            ->get(route('cp.client-goods-takes.create', ['vendor_id' => $worker->id]))
            ->assertOk()
            ->assertSee('value="other" selected', false)
            ->assertSee('الموظفون')
            ->assertSee('الموردون')
            ->assertSee('الأشخاص');

        $this->actingAs($this->user)
            ->post(route('cp.client-goods-takes.store'), [
                'party_key' => 'vendor:'.$worker->id,
                'category' => 'other',
                'title' => 'سلفة عينية',
                'amount' => 100,
                'currency_id' => $this->ils->id,
                'taken_on' => '05\\09\\2026',
            ])
            ->assertRedirect(route('cp.workers.show', $worker));

        $this->assertDatabaseHas('client_goods_takes', [
            'party_type' => 'vendor',
            'party_id' => $worker->id,
            'client_id' => null,
            'title' => 'سلفة عينية',
        ]);
        $this->assertSame('2026-09-05', ClientGoodsTake::query()->where('title', 'سلفة عينية')->firstOrFail()->taken_on->toDateString());
        $this->assertSame('400.00', $worker->fresh()->outstandingAmount($this->ils->id));

        $this->actingAs($this->user)
            ->get(route('cp.client-goods-takes.create', ['person_id' => $person->id]))
            ->assertOk()
            ->assertSee('value="other" selected', false);

        $this->actingAs($this->user)
            ->post(route('cp.client-goods-takes.store'), [
                'party_key' => 'person:'.$person->id,
                'category' => 'other',
                'title' => 'هدية',
                'amount' => 40,
                'currency_id' => $this->ils->id,
                'taken_on' => '2026-09-06',
            ])
            ->assertRedirect(route('cp.persons.show', $person));

        $this->assertSame('-40.00', $person->fresh()->netAmount($this->ils->id));
        $this->actingAs($this->user)
            ->get(route('cp.persons.show', $person))
            ->assertOk()
            ->assertSee('هدية');
    }

    protected function ledgerTotal(): string
    {
        return Money::of(LedgerEntry::query()->sum('amount'));
    }
}
