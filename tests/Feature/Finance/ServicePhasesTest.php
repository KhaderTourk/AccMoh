<?php

namespace Tests\Feature\Finance;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\ServiceGroup;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePhasesTest extends TestCase
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

    protected function addService(array $extra): ClientService
    {
        $this->actingAs($this->user)->post(route('cp.client-services.store'), $extra + [
            'currency_id' => $this->ils->id, 'service_date' => '2026-10-01',
        ])->assertRedirect()->assertSessionHasNoErrors();
        TenantContext::set((int) $this->user->tenant_id);

        return ClientService::query()->latest('id')->firstOrFail();
    }

    public function test_service_dialog_creates_group_phases_and_price(): void
    {
        $this->actingAs($this->user)->post(route('cp.settings.services.store'), [
            'name' => 'ديكور وواجهات', 'new_group_name' => 'ديكور', 'billing' => 'once',
            'has_phases' => 1, 'default_price' => '1500', 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        TenantContext::set((int) $this->user->tenant_id);

        $type = ServiceType::query()->where('name', 'ديكور وواجهات')->firstOrFail();
        $this->assertTrue($type->has_phases);
        $this->assertSame('1500.00', (string) $type->default_price);
        $this->assertSame('ديكور', ServiceGroup::query()->find($type->service_group_id)?->name);

        $this->actingAs($this->user)->get(route('cp.settings.index'))->assertOk()->assertSee('تصميم · تنفيذ');
    }

    public function test_work_phase_saved_only_for_phased_services_and_report_splits_it(): void
    {
        $client = Client::query()->create(['name' => 'زبون المراحل', 'is_active' => true]);
        $phased = ServiceType::query()->create(['name' => 'تغليف', 'billing' => 'once', 'has_phases' => true, 'is_active' => true]);
        $plain = ServiceType::query()->create(['name' => 'شعار', 'billing' => 'once', 'is_active' => true]);

        $a = $this->addService(['client_id' => $client->id, 'service_type_id' => $phased->id, 'work_phase' => 'design', 'amount' => 100]);
        $this->assertSame('design', $a->work_phase);
        $b = $this->addService(['client_id' => $client->id, 'service_type_id' => $phased->id, 'work_phase' => 'execution', 'amount' => 250]);
        $c = $this->addService(['client_id' => $client->id, 'service_type_id' => $plain->id, 'work_phase' => 'design', 'amount' => 70]);
        $this->assertNull($c->work_phase);

        // تعديل بدون الحقل بيحافظ على القيمة.
        $this->actingAs($this->user)->put(route('cp.client-services.update', $b), [
            'client_id' => $client->id, 'service_type_id' => $phased->id, 'amount' => 260,
            'currency_id' => $this->ils->id, 'service_date' => '2026-10-02',
        ])->assertRedirect()->assertSessionHasNoErrors();
        TenantContext::set((int) $this->user->tenant_id);
        $this->assertSame('execution', $b->fresh()->work_phase);

        $phased->update(['is_active' => false]); // الموقوفة بتضل بالتقرير
        $res = $this->actingAs($this->user)->get(route('cp.reports.services'))->assertOk();
        $res->assertSee('تغليف')->assertSee('موقوفة')->assertSee('100.00')->assertSee('260.00')->assertSee('430.00');
    }
}
