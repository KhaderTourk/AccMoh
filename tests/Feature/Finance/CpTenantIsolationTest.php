<?php

namespace Tests\Feature\Finance;

use App\Enums\VendorType;
use App\Models\Client;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CpTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cp_cannot_open_or_edit_records_of_another_tenant(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $other = Tenant::query()->create(['name' => 'نسخة أخرى', 'slug' => 'other', 'is_active' => true, 'business_enabled' => true]);
        TenantContext::set($other->id);
        $client = Client::query()->create(['name' => 'زبون نسخة أخرى', 'is_active' => true]);
        $person = Person::query()->create(['name' => 'شخص نسخة أخرى', 'is_active' => true]);
        $worker = Vendor::query()->create(['name' => 'موظف نسخة أخرى', 'type' => VendorType::Worker, 'is_active' => true]);
        TenantContext::clear();

        $this->actingAs($user)->get("/cp/clients/{$client->id}")->assertNotFound();
        $this->actingAs($user)->get("/cp/clients/{$client->id}/edit")->assertNotFound();
        $this->actingAs($user)->put("/cp/clients/{$client->id}", ['name' => 'اختراق'])->assertNotFound();
        $this->actingAs($user)->get("/cp/persons/{$person->id}")->assertNotFound();
        $this->actingAs($user)->get("/cp/workers/{$worker->id}")->assertNotFound();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'زبون نسخة أخرى']);
    }

    public function test_cp_still_redirects_guests_to_login(): void
    {
        $this->get('/cp/clients/1')->assertRedirect(route('cp.login'));
    }
}
