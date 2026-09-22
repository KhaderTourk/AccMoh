<?php

namespace Tests\Feature\Finance;

use App\Enums\VendorType;
use App\Models\Client;
use App\Models\Fund;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
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

    public function test_archived_parties_remain_visible_in_index_tables(): void
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
            ->assertSee('زبون للأرشفة')
            ->assertSee('مؤرشف');
        $this->actingAs($this->user)->get(route('cp.persons.index'))
            ->assertOk()
            ->assertSee('شخص للأرشفة')
            ->assertSee('مؤرشف');
        $this->actingAs($this->user)->get(route('cp.workers.index'))
            ->assertOk()
            ->assertSee('موظف للأرشفة')
            ->assertSee('مؤرشف');
        $this->actingAs($this->user)->get(route('cp.suppliers.index'))
            ->assertOk()
            ->assertSee('مورد للأرشفة')
            ->assertSee('مؤرشف');

        $this->assertFalse($client->fresh()->is_active);
        $this->assertFalse($person->fresh()->is_active);
        $this->assertFalse($worker->fresh()->is_active);
        $this->assertFalse($supplier->fresh()->is_active);
    }
}
