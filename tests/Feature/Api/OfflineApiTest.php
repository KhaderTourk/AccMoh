<?php

namespace Tests\Feature\Api;

use App\Enums\LoanDirection;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FamilyMember;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\ClientWorkService;
use App\Services\Finance\FamilyLoanService;
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
                'unpaid_services',
                'family_members',
                'balances',
            ]);
    }

    public function test_client_payment_is_idempotent(): void
    {
        Sanctum::actingAs($this->user);

        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        $service = app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'إعلان',
            'amount' => 500,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);

        $operationId = (string) Str::uuid();
        $payload = [
            'operation_id' => $operationId,
            'client_id' => $client->id,
            'amount' => 200,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->cash->id,
            'payment_date' => '2026-08-21',
            'payer_name' => 'محمد',
        ];

        $first = $this->postJson('/api/v1/payments', $payload);
        $first->assertCreated()->assertJsonPath('replayed', false);

        $second = $this->postJson('/api/v1/payments', $payload);
        $second->assertOk()->assertJsonPath('replayed', true);

        $this->assertDatabaseCount('client_payments', 1);
    }

    public function test_sync_push_expense_and_returns_snapshot(): void
    {
        Sanctum::actingAs($this->user);

        // Seed cash via family borrow so expense has balance
        $member = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);
        app(FamilyLoanService::class)->create([
            'family_member_id' => $member->id,
            'direction' => LoanDirection::Borrowed,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'loan_date' => '2026-08-20',
        ]);

        $opId = (string) Str::uuid();
        $response = $this->postJson('/api/v1/sync/push', [
            'device_id' => 'phone-1',
            'operations' => [[
                'operation_id' => $opId,
                'type' => 'expense',
                'payload' => [
                    'fund_id' => $this->family->id,
                    'description' => 'مصروف يومي',
                    'amount' => 50,
                    'currency_id' => $this->ils->id,
                    'payment_method_id' => $this->cash->id,
                    'expense_date' => '2026-08-21',
                ],
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('results.0.status', 'completed')
            ->assertJsonStructure(['snapshot' => ['balances', 'catalog']]);

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_family_loan_via_api(): void
    {
        Sanctum::actingAs($this->user);
        $member = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);

        $this->postJson('/api/v1/family-loans', [
            'operation_id' => (string) Str::uuid(),
            'family_member_id' => $member->id,
            'direction' => 'borrowed',
            'amount' => 300,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->bank->id,
            'loan_date' => '2026-08-21',
        ])->assertCreated();

        $this->assertDatabaseCount('family_loans', 1);
        $this->getJson('/api/v1/balances')->assertOk()->assertJsonPath('balances.grand.ILS', '300.00');
    }
}
