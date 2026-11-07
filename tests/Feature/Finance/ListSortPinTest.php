<?php

namespace Tests\Feature\Finance;

use App\Enums\VendorType;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListSortPinTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_pin_toggles_for_each_party_kind_and_lists_render(): void
    {
        $client = Client::query()->create(['name' => 'زبون مثبّت', 'is_active' => true]);
        $person = Person::query()->create(['name' => 'شخص مثبّت', 'is_active' => true]);
        $worker = Vendor::query()->create(['name' => 'موظف مثبّت', 'type' => VendorType::Worker, 'is_active' => true]);

        foreach ([['client', $client], ['person', $person], ['vendor', $worker]] as [$kind, $model]) {
            $this->actingAs($this->user)->postJson(route('cp.pin.toggle', ['kind' => $kind, 'id' => $model->id]))
                ->assertOk()->assertJson(['pinned' => true]);
            $this->assertTrue($model->fresh()->is_pinned);
        }
        $this->actingAs($this->user)->postJson(route('cp.pin.toggle', ['kind' => 'person', 'id' => $person->id]))
            ->assertJson(['pinned' => false]);

        $this->actingAs($this->user)->get(route('cp.clients.index'))->assertOk()
            ->assertSee('"pinned":true', false)->assertSee('cp-list.js', false)->assertSee('عليه رصيد');
        $this->actingAs($this->user)->get(route('cp.workers.index'))->assertOk()
            ->assertSee('"last_move":null', false)->assertDontSee('cl-legend', false);
        $this->actingAs($this->user)->get(route('cp.persons.index'))->assertOk()->assertSee('إله رصيد');
    }

    public function test_amounts_show_number_then_currency(): void
    {
        $this->assertSame('120.00 $', Currency::byCode('USD')->format(120));
        $this->assertSame('742.50 ₪', Currency::byCode('ILS')->format(742.5));
    }
}
