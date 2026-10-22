<?php

namespace Tests\Feature\Api;

use App\Models\ApiOperation;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Finance\BalanceService;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiOperationLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Currency $ils;

    protected PaymentMethod $cash;

    protected PaymentMethod $bank;

    protected Fund $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $this->user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Fund::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        ServiceType::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $this->cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $this->bank = PaymentMethod::query()->where('slug', 'bank')->firstOrFail();
        $this->business = Fund::withoutGlobalScopes()->where('tenant_id', $this->user->tenant_id)->where('slug', 'business')->firstOrFail();
    }

    public function test_reads_and_failed_writes_are_not_logged(): void
    {
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/clients')->assertOk();
        $this->postJson('/api/v1/clients', ['name' => ''])->assertStatus(422);

        $this->assertSame(0, ApiOperation::query()->withoutGlobalScopes()->count());
    }

    public function test_create_is_logged_shown_in_cp_and_undone(): void
    {
        Sanctum::actingAs($this->user);
        $clientId = $this->postJson('/api/v1/clients', ['name' => 'زبون API'])->assertCreated()->json('data.id');

        $op = ApiOperation::query()->withoutGlobalScopes()->sole();
        $this->assertSame((int) $this->user->tenant_id, (int) $op->tenant_id);
        $this->assertSame('api.clients.store', $op->route_name);
        $this->assertSame('created', $op->changes[0]['op']);
        $this->assertSame('إضافة زبون', $op->label());

        $this->actingAs($this->user, 'web')->get(route('cp.api-operations.index'))->assertOk()
            ->assertSee('عمليات الـ API')->assertSee('إضافة زبون');
        $this->actingAs($this->user, 'web')->get(route('cp.api-operations.show', $op))->assertOk()->assertSee('زبون API');

        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $op))->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('clients', ['id' => $clientId]);
        $this->assertNotNull($op->fresh()->undone_at);

        // Second undo is refused.
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $op))->assertSessionHas('error');
    }

    public function test_undo_payment_update_restores_books(): void
    {
        Sanctum::actingAs($this->user);
        TenantContext::set((int) $this->user->tenant_id);
        $client = Client::query()->create(['name' => 'زبون', 'is_active' => true]);
        TenantContext::clear();

        $paymentId = $this->postJson('/api/v1/payments/incoming', [
            'party_type' => 'client', 'party_id' => $client->id, 'fund_id' => $this->business->id,
            'payment_method_id' => $this->cash->id, 'currency_id' => $this->ils->id,
            'amount' => 200, 'occurred_on' => '2026-10-02',
        ])->assertCreated()->json('data.id');

        $ledgerBefore = LedgerEntry::query()->withoutGlobalScopes()->count();
        $this->putJson("/api/v1/payments/{$paymentId}", [
            'party_type' => 'client', 'party_id' => $client->id, 'fund_id' => $this->business->id,
            'payment_method_id' => $this->cash->id, 'currency_id' => $this->ils->id,
            'amount' => 350, 'occurred_on' => '2026-10-02',
        ])->assertOk();
        $this->assertSame('350.00', $this->cashBalance());

        $update = ApiOperation::query()->withoutGlobalScopes()->latest('id')->first();
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $update))->assertSessionHas('success');

        $this->assertSame('200.00', $this->cashBalance());
        $this->assertSame($ledgerBefore, LedgerEntry::query()->withoutGlobalScopes()->count());
        $this->assertFalse((bool) CashPayment::query()->withoutGlobalScopes()->find($paymentId)->is_reversed);
        $this->assertSame(1, CashPayment::query()->withoutGlobalScopes()->count());

        // Then undo the original create: books go back to zero.
        $create = ApiOperation::query()->withoutGlobalScopes()->pending()->sole();
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $create))->assertSessionHas('success');
        $this->assertSame('0.00', $this->cashBalance());
        $this->assertSame(0, CashPayment::query()->withoutGlobalScopes()->count());
    }

    public function test_undo_is_blocked_when_record_changed_later(): void
    {
        Sanctum::actingAs($this->user);
        $clientId = $this->postJson('/api/v1/clients', ['name' => 'زبون'])->json('data.id');
        $this->putJson("/api/v1/clients/{$clientId}", ['name' => 'زبون معدّل'])->assertOk();

        [$create, $update] = ApiOperation::query()->withoutGlobalScopes()->orderBy('id')->get()->all();

        // The create cannot be undone while the later update is in place.
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $create))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'عُدّل بعد هذه العملية'));
        $this->assertDatabaseHas('clients', ['id' => $clientId]);
        $this->actingAs($this->user, 'web')->get(route('cp.api-operations.show', $create))->assertOk()->assertSee('لا يمكن التراجع الآن');

        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $update))->assertSessionHas('success');
        $this->assertDatabaseHas('clients', ['id' => $clientId, 'name' => 'زبون']);
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $create))->assertSessionHas('success');
        $this->assertDatabaseMissing('clients', ['id' => $clientId]);
    }

    public function test_undo_client_create_blocked_when_cp_added_movements(): void
    {
        Sanctum::actingAs($this->user);
        $clientId = $this->postJson('/api/v1/clients', ['name' => 'زبون'])->json('data.id');

        // A service added later from the CP (not through the API) now depends on the client.
        $this->actingAs($this->user, 'web')->post(route('cp.client-services.store'), [
            'client_id' => $clientId, 'service_type_id' => ServiceType::query()->withoutGlobalScopes()->value('id'),
            'amount' => 100, 'currency_id' => $this->ils->id, 'service_date' => '2026-10-01',
        ])->assertRedirect();

        $op = ApiOperation::query()->withoutGlobalScopes()->sole();
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $op))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'خدمات'));
        $this->assertDatabaseHas('clients', ['id' => $clientId]);
    }

    public function test_transfer_memo_group_and_role_permissions_are_undoable(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/balances/opening', [
            'fund_id' => $this->business->id, 'payment_method_id' => $this->cash->id,
            'currency_id' => $this->ils->id, 'amount' => 1000, 'occurred_on' => '2026-10-01',
        ])->assertCreated();
        $transferId = $this->postJson('/api/v1/transfers', [
            'fund_id' => $this->business->id, 'from_payment_method_id' => $this->cash->id,
            'to_payment_method_id' => $this->bank->id, 'amount' => 400,
            'currency_id' => $this->ils->id, 'transfer_date' => '2026-10-02',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/transfers/{$transferId}/reverse")->assertOk();
        $this->assertSame('1000.00', $this->cashBalance());

        $reverse = ApiOperation::query()->withoutGlobalScopes()->latest('id')->first();
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $reverse))->assertSessionHas('success');
        $this->assertSame('600.00', $this->cashBalance());

        // Two-leg balance transfer, then edit (one mass update on both legs), then undo the edit.
        $a = $this->postJson('/api/v1/clients', ['name' => 'أ'])->json('data.id');
        $b = $this->postJson('/api/v1/clients', ['name' => 'ب'])->json('data.id');
        $memoId = $this->postJson('/api/v1/balance-memos/transfer', [
            'party_type' => 'client', 'party_id' => $a, 'target_party_type' => 'client', 'target_party_id' => $b,
            'direction' => 'owed_to_us', 'amount' => 10, 'currency_id' => $this->ils->id, 'memo_date' => '2026-10-01',
        ])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/balance-memos/{$memoId}", [
            'direction' => 'owed_to_us', 'amount' => 25, 'currency_id' => $this->ils->id, 'memo_date' => '2026-10-01',
        ])->assertOk();
        $edit = ApiOperation::query()->withoutGlobalScopes()->latest('id')->first();
        $this->assertCount(2, $edit->changes);
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $edit))->assertSessionHas('success');
        $this->assertSame(2, \App\Models\PartyBalanceMemo::query()->withoutGlobalScopes()->where('amount', 10)->count());

        // Role permissions (pivot sync) are restored too.
        $role = Role::query()->create(['name' => 'دور', 'slug' => 'r1']);
        $permissionIds = $this->getJson('/api/v1/permissions')->json('data.*.id');
        $this->putJson("/api/v1/roles/{$role->id}", ['name' => 'دور', 'slug' => 'r1', 'permissions' => $permissionIds])->assertOk();
        $this->assertCount(count($permissionIds), $role->permissions()->get());
        $roleOp = ApiOperation::query()->withoutGlobalScopes()->latest('id')->first();
        $this->actingAs($this->user, 'web')->post(route('cp.api-operations.undo', $roleOp))->assertSessionHas('success');
        $this->assertCount(0, $role->permissions()->get());
    }

    public function test_operations_of_another_tenant_are_hidden(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/clients', ['name' => 'زبون'])->assertCreated();
        $op = ApiOperation::query()->withoutGlobalScopes()->sole();

        $other = \App\Models\Tenant::query()->create(['name' => 'أخرى', 'slug' => 'o', 'is_active' => true, 'business_enabled' => true]);
        $stranger = User::query()->create([
            'name' => 'غريب', 'email' => 's@example.com', 'password' => 'password',
            'tenant_id' => $other->id, 'is_active' => true, 'is_super_admin' => true,
        ]);
        $this->actingAs($stranger, 'web')->get(route('cp.api-operations.show', $op))->assertNotFound();
        $this->actingAs($stranger, 'web')->post(route('cp.api-operations.undo', $op))->assertNotFound();
        $this->assertNull($op->fresh()->undone_at);
    }

    protected function cashBalance(): string
    {
        TenantContext::set((int) $this->user->tenant_id);
        $balance = app(BalanceService::class)->cash($this->business->id, $this->cash->id, $this->ils->id);
        TenantContext::clear();

        return $balance;
    }
}
