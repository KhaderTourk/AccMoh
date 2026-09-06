<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentDirection;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FamilyMember;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\CashPaymentService;
use App\Services\Finance\ClientWorkService;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfflineApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Currency $ils;
    protected Currency $usd;
    protected PaymentMethod $cash;
    protected PaymentMethod $bank;
    protected Fund $family;
    protected Fund $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);

        $this->user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $this->usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $this->bank = PaymentMethod::query()->where('slug', 'bank')->firstOrFail();
        $this->family = Fund::family();
        $this->business = Fund::business();
    }

    public function test_login_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
            'device_name' => 'test-phone',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_bootstrap_requires_auth_and_returns_catalog(): void
    {
        $this->getJson('/api/v1/bootstrap')->assertUnauthorized();

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonStructure([
                'catalog' => ['currencies', 'payment_methods', 'funds'],
                'clients',
                'persons',
                'unpaid_services',
                'balances',
            ]);
    }

    public function test_incoming_payment_is_idempotent(): void
    {
        Sanctum::actingAs($this->user);

        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'إعلان',
            'amount' => 500,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);

        $operationId = (string) Str::uuid();
        $payload = [
            'operation_id' => $operationId,
            'direction' => 'incoming',
            'name' => 'محمد',
            'client_id' => $client->id,
            'amount' => 200,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-21',
        ];

        $first = $this->postJson('/api/v1/cash-payments', $payload);
        $first->assertCreated()->assertJsonPath('replayed', false);

        $second = $this->postJson('/api/v1/cash-payments', $payload);
        $second->assertOk()->assertJsonPath('replayed', true);

        $this->assertDatabaseCount('cash_payments', 1);
    }

    public function test_sync_push_outgoing_and_returns_snapshot(): void
    {
        Sanctum::actingAs($this->user);

        $member = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);
        app(CashPaymentService::class)->record([
            'direction' => PaymentDirection::Incoming,
            'party_type' => 'person',
            'party_id' => $member->id,
            'name' => 'أحمد',
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-20',
        ]);

        $opId = (string) Str::uuid();
        $response = $this->postJson('/api/v1/sync/push', [
            'device_id' => 'phone-1',
            'operations' => [[
                'operation_id' => $opId,
                'type' => 'outgoing_payment',
                'payload' => [
                    'name' => 'مصروف يومي',
                    'fund_id' => $this->family->id,
                    'amount' => 50,
                    'currency_id' => $this->ils->id,
                    'payment_method_id' => $this->cash->id,
                    'occurred_on' => '2026-08-21',
                    'notes' => 'ملاحظة أوفلاين',
                ],
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.0.status', 'completed')
            ->assertJsonStructure(['snapshot' => ['balances', 'catalog']]);

        $this->assertDatabaseCount('cash_payments', 2);
        $this->assertDatabaseHas('cash_payments', ['notes' => 'ملاحظة أوفلاين']);
    }

    public function test_person_incoming_via_api(): void
    {
        Sanctum::actingAs($this->user);
        $member = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);

        $this->postJson('/api/v1/cash-payments', [
            'operation_id' => (string) Str::uuid(),
            'direction' => 'incoming',
            'name' => 'أحمد',
            'person_id' => $member->id,
            'amount' => 300,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->bank->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-21',
        ])->assertCreated();

        $this->assertDatabaseCount('cash_payments', 1);
        $this->getJson('/api/v1/balances')->assertOk()->assertJsonPath('balances.grand.ILS', '300.00');
    }
}
