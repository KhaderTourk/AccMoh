<?php

namespace Tests\Feature\Finance;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientSubscription;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** واجهة الزبائن v2: الاشتراك من «تعديل الزبون» وإضافة الشهر من نافذة الخدمة (سجل القرارات 15–18). */
class ClientsScreenV2Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Currency $ils;

    protected ServiceType $type;

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
        $this->type = ServiceType::query()->where('is_active', true)->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        TenantContext::clear();
        parent::tearDown();
    }

    protected function editClient(Client $client, array $subs, bool $on = true, array $extra = [])
    {
        return $this->actingAs($this->user)->put(route('cp.clients.update', $client), [
            'name' => $client->name, 'return' => 'list', 'subs_sent' => 1, 'subs_on' => $on ? 1 : 0, 'subs' => $subs,
        ] + $extra);
    }

    public function test_client_edit_adds_changes_and_removes_monthly_subscriptions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));
        $client = Client::query()->create(['name' => 'إيليت بالاس', 'is_active' => true]);

        $this->editClient($client, [['type' => $this->type->id, 'amount' => 900]], true, ['stay' => 1])
            ->assertRedirect(route('cp.clients.index', ['flash' => $client->id, 'edit' => $client->id]));
        $sub = ClientSubscription::query()->firstOrFail();
        $this->assertSame('2026-11-01', $sub->starts_on->toDateString());
        $this->assertSame('900.00', $sub->amount);

        $this->editClient($client, [['type' => $this->type->id, 'amount' => 950]]);
        $this->assertSame('950.00', $sub->fresh()->amount);
        $this->assertSame(1, ClientSubscription::query()->count());

        $this->editClient($client, [], false);
        $this->assertSame(0, ClientSubscription::query()->count());
    }

    public function test_full_month_is_dated_on_the_last_day_and_cannot_be_added_twice_by_accident(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));
        $client = Client::query()->create(['name' => 'إيليت بالاس', 'is_active' => true]);
        $sub = ClientSubscription::query()->create([
            'client_id' => $client->id, 'service_type_id' => $this->type->id, 'title' => $this->type->name,
            'amount' => 900, 'currency_id' => $this->ils->id, 'starts_on' => '2026-10-01',
        ]);
        ClientService::query()->create([
            'client_id' => $client->id, 'service_type_id' => $this->type->id, 'title' => $this->type->name, 'amount' => 900,
            'currency_id' => $this->ils->id, 'service_date' => '2026-10-31', 'status' => 'completed',
        ])->forceFill(['subscription_id' => $sub->id, 'period' => '2026-10'])->save();

        $this->actingAs($this->user)->post(route('cp.subscriptions.month', $sub), [
            'period' => '2026-11', 'amount' => 900, 'currency_id' => $this->ils->id, 'notes' => 'اشتراك 11/2026',
        ])->assertRedirect(route('cp.clients.show', $client));
        $month = ClientService::query()->where('period', '2026-11')->firstOrFail();
        $this->assertSame('2026-11-30', $month->service_date->toDateString());
        $this->assertSame($sub->id, $month->subscription_id);
        $this->assertSame('اشتراك 11/2026', $month->notes);

        $this->actingAs($this->user)->post(route('cp.subscriptions.month', $sub), [
            'period' => '2026-11', 'amount' => 900, 'currency_id' => $this->ils->id,
        ])->assertSessionHasErrors('period');

        $this->actingAs($this->user)->post(route('cp.subscriptions.month', $sub), [
            'period' => '2026-11', 'amount' => 900, 'currency_id' => $this->ils->id, 'again' => 1,
        ])->assertRedirect();
        $this->assertSame(3, ClientService::query()->count());
        $this->assertSame(1, ClientService::query()->where('period', '2026-11')->count());
    }

    public function test_first_month_moves_the_start_and_last_month_stops_on_the_stop_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));
        $client = Client::query()->create(['name' => 'حلا السلطان', 'is_active' => true]);
        $sub = ClientSubscription::query()->create([
            'client_id' => $client->id, 'service_type_id' => $this->type->id, 'title' => $this->type->name,
            'amount' => 900, 'currency_id' => $this->ils->id, 'starts_on' => '2026-11-01',
        ]);

        // بلّش يوم 10 ووقف يوم 15 بنفس الشهر: 6 أيام = 180، بتاريخ يوم ما وقف.
        $this->actingAs($this->user)->post(route('cp.subscriptions.month', $sub), [
            'period' => '2026-11', 'amount' => 180, 'currency_id' => $this->ils->id,
            'start_day' => 10, 'stop' => 1, 'end_day' => 15,
        ])->assertRedirect();
        $sub->refresh();
        $this->assertSame('2026-11-10', $sub->starts_on->toDateString());
        $this->assertSame('2026-11-15', $sub->ends_on->toDateString());
        $month = ClientService::query()->firstOrFail();
        $this->assertSame('2026-11-15', $month->service_date->toDateString());
        $this->assertSame('180.00', $month->amount);
    }

    public function test_foreign_currency_month_is_converted_with_the_given_rate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));
        $usd = Currency::query()->where('code', 'USD')->first();
        if (! $usd) {
            $this->markTestSkipped('ما في دولار بالكتالوج.');
        }
        $client = Client::query()->create(['name' => 'زبون دولار', 'is_active' => true]);
        $sub = ClientSubscription::query()->create([
            'client_id' => $client->id, 'service_type_id' => $this->type->id, 'title' => $this->type->name,
            'amount' => 900, 'currency_id' => $this->ils->id, 'starts_on' => '2026-11-01',
        ]);
        $this->actingAs($this->user)->post(route('cp.subscriptions.month', $sub), [
            'period' => '2026-11', 'source_amount' => 250, 'exchange_rate' => 3.7, 'currency_id' => $usd->id,
        ])->assertRedirect();
        $month = ClientService::query()->firstOrFail();
        $this->assertSame('925.00', $month->amount);
        $this->assertSame($this->ils->id, $month->currency_id);
    }

    public function test_clients_screen_shows_the_new_table_and_header_numbers(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));
        $client = Client::query()->create(['name' => 'مطعم الريف', 'is_active' => true]);
        ClientService::query()->create([
            'client_id' => $client->id, 'service_type_id' => $this->type->id, 'title' => $this->type->name, 'amount' => 500,
            'currency_id' => $this->ils->id, 'service_date' => '2026-11-03', 'status' => 'completed',
        ]);

        $res = $this->actingAs($this->user)->get(route('cp.clients.index'))->assertOk();
        $res->assertSee('id="clCards"', false)->assertSee('id="clHead"', false);
        $head = $res->viewData('head');
        $this->assertEqualsWithDelta(500.0, $head['owed'], 0.001);
        $this->assertEqualsWithDelta(500.0, $head['services'], 0.001);
        $this->assertEqualsWithDelta(500.0, $head['debtChange'], 0.001);
        $this->assertSame(1, $head['unpaidMonth']);

        $this->actingAs($this->user)->get(route('cp.clients.show', $client))->assertOk()->assertSee('cp-date.js', false);
    }
}
