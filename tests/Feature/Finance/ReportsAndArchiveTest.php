<?php

namespace Tests\Feature\Finance;

use App\Enums\PaymentDirection;
use App\Enums\VendorType;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Finance\CashPaymentService;
use App\Services\Finance\ClientWorkService;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAndArchiveTest extends TestCase
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
    }

    public function test_reports_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get(route('cp.reports.index'))
            ->assertOk()
            ->assertSee('التقارير')
            ->assertSee('cp-table-sort.js');
    }

    public function test_client_form_puts_organization_before_name(): void
    {
        $html = $this->actingAs($this->user)
            ->get(route('cp.clients.create'))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(mb_strpos($html, '>الاسم'), mb_strpos($html, '>الجهة'));
    }

    public function test_archived_parties_are_hidden_from_index_tables(): void
    {
        TenantContext::set((int) $this->user->tenant_id);
        $client = Client::query()->create(['name' => 'زبون للأرشفة', 'company_name' => 'جهة الأرشفة', 'is_active' => true]);
        $person = Person::query()->create(['name' => 'شخص للأرشفة', 'is_active' => true]);
        $worker = Vendor::query()->create(['name' => 'موظف للأرشفة', 'type' => VendorType::Worker, 'is_active' => true]);
        $supplier = Vendor::query()->create(['name' => 'مورد للأرشفة', 'type' => VendorType::Supplier, 'is_active' => true]);
        TenantContext::clear();

        $this->actingAs($this->user)->delete(route('cp.clients.destroy', $client))->assertRedirect(route('cp.clients.index'));
        $this->actingAs($this->user)->delete(route('cp.persons.destroy', $person))->assertRedirect(route('cp.persons.index'));
        $this->actingAs($this->user)->delete(route('cp.workers.destroy', $worker))->assertRedirect(route('cp.workers.index'));
        $this->actingAs($this->user)->delete(route('cp.suppliers.destroy', $supplier))->assertRedirect(route('cp.suppliers.index'));

        $this->actingAs($this->user)->get(route('cp.clients.index'))
            ->assertOk()
            ->assertDontSee('زبون للأرشفة');
        $this->actingAs($this->user)->get(route('cp.persons.index'))
            ->assertOk()
            ->assertDontSee('شخص للأرشفة');
        $this->actingAs($this->user)->get(route('cp.workers.index'))
            ->assertOk()
            ->assertDontSee('موظف للأرشفة');
        $this->actingAs($this->user)->get(route('cp.suppliers.index'))
            ->assertOk()
            ->assertDontSee('مورد للأرشفة');

        $this->assertFalse($client->fresh()->is_active);
        $this->assertFalse($person->fresh()->is_active);
        $this->assertFalse($worker->fresh()->is_active);
        $this->assertFalse($supplier->fresh()->is_active);
    }

    public function test_archive_toggle_shows_archived_people_users_and_movements(): void
    {
        TenantContext::set((int) $this->user->tenant_id);
        $client = Client::query()->create(['name' => 'زبون مخفي', 'is_active' => false]);
        Person::query()->create(['name' => 'شخص مخفي', 'is_active' => false]);
        Vendor::query()->create(['name' => 'موظف مخفي', 'type' => VendorType::Worker, 'is_active' => false]);
        Vendor::query()->create(['name' => 'مورد مخفي', 'type' => VendorType::Supplier, 'is_active' => false]);
        User::query()->create([
            'name' => 'مستخدم مؤرشف',
            'email' => 'archived-user@example.com',
            'password' => 'secret',
            'is_active' => false,
            'is_super_admin' => false,
            'tenant_id' => $this->user->tenant_id,
            'role_id' => $this->user->role_id,
        ]);
        $ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        app(CashPaymentService::class)->record([
            'direction' => PaymentDirection::Incoming,
            'party_type' => 'client',
            'party_id' => $client->id,
            'name' => 'دفعة الزبون المخفي',
            'amount' => 25,
            'currency_id' => $ils->id,
            'payment_method_id' => $cash->id,
            'occurred_on' => now()->toDateString(),
        ]);
        app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'خدمة الزبون المخفي',
            'amount' => 40,
            'currency_id' => $ils->id,
            'service_date' => now()->toDateString(),
        ]);
        TenantContext::clear();

        $this->actingAs($this->user)->get(route('cp.dashboard'))->assertOk()->assertSee('إظهار/إخفاء المؤرشف');
        $this->actingAs($this->user)->get(route('cp.clients.index'))->assertDontSee('زبون مخفي');
        $this->actingAs($this->user)->get(route('cp.persons.index'))->assertDontSee('شخص مخفي');
        $this->actingAs($this->user)->get(route('cp.workers.index'))->assertDontSee('موظف مخفي');
        $this->actingAs($this->user)->get(route('cp.suppliers.index'))->assertDontSee('مورد مخفي');
        $this->actingAs($this->user)->get(route('cp.users.index'))->assertDontSee('مستخدم مؤرشف');
        $this->actingAs($this->user)->get(route('cp.payments.incoming'))->assertDontSee('دفعة الزبون المخفي');
        $this->actingAs($this->user)->get(route('cp.ledger.index'))->assertDontSee('دفعة الزبون المخفي');
        $this->actingAs($this->user)->get(route('cp.client-services.index'))->assertDontSee('خدمة الزبون المخفي');

        $shown = ['cp.show_archived' => true];
        $this->actingAs($this->user)->withSession($shown)->get(route('cp.clients.index'))->assertSee('زبون مخفي')->assertSee('مؤرشف');
        $this->actingAs($this->user)->withSession($shown)->get(route('cp.persons.index'))->assertSee('شخص مخفي');
        $this->actingAs($this->user)->withSession($shown)->get(route('cp.workers.index'))->assertSee('موظف مخفي');
        $this->actingAs($this->user)->withSession($shown)->get(route('cp.suppliers.index'))->assertSee('مورد مخفي');
        $this->actingAs($this->user)->withSession($shown)->get(route('cp.users.index'))->assertSee('مستخدم مؤرشف');
        $this->actingAs($this->user)->withSession($shown)->get(route('cp.payments.incoming'))->assertSee('دفعة الزبون المخفي');
        $this->actingAs($this->user)->withSession($shown)->get(route('cp.ledger.index'))->assertSee('دفعة الزبون المخفي');
        $this->actingAs($this->user)->withSession($shown)->get(route('cp.client-services.index'))->assertSee('خدمة الزبون المخفي');

        $this->actingAs($this->user)
            ->from(route('cp.dashboard'))
            ->post(route('cp.archive-visibility'))
            ->assertRedirect(route('cp.dashboard'));
    }
}
