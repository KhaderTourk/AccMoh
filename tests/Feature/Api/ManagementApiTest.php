<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ManagementApiTest extends TestCase
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
        Fund::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        ServiceType::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);

        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $this->usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $this->bank = PaymentMethod::query()->where('slug', 'bank')->firstOrFail();
        $this->family = Fund::withoutGlobalScopes()->where('tenant_id', $this->user->tenant_id)->where('slug', 'family')->firstOrFail();
        $this->business = Fund::withoutGlobalScopes()->where('tenant_id', $this->user->tenant_id)->where('slug', 'business')->firstOrFail();
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/v1/clients')->assertUnauthorized();
        $this->getJson('/api/v1/lookups')->assertUnauthorized();
    }

    public function test_lookups_dashboard_ledger_reports_and_balances(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/lookups')->assertOk()
            ->assertJsonStructure(['data' => ['currencies', 'paymentMethods', 'funds', 'serviceTypes', 'parties' => ['clients', 'persons', 'vendors'], 'enums', 'user']]);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonStructure(['data' => ['snapshot', 'counts', 'recent']]);
        $this->getJson('/api/v1/ledger?from=2026-01-01&to=2026-12-31')->assertOk()->assertJsonStructure(['entries' => ['data', 'total'], 'totals']);
        $this->getJson('/api/v1/reports')->assertOk()->assertJsonStructure(['data' => ['snapshot', 'clientSummary', 'incoming', 'outgoing', 'profitRows']]);
        $this->getJson('/api/v1/balances/snapshot')->assertOk()->assertJsonStructure(['data' => ['currencies', 'grand']]);
    }

    public function test_full_client_flow_without_delete(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/clients', ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');

        $clientId = $this->postJson('/api/v1/clients', [
            'name' => 'محمد',
            'company_name' => 'شركة النور',
            'phone' => '0599123456',
        ])->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');

        // Update without is_active keeps the client active.
        $this->putJson("/api/v1/clients/{$clientId}", ['name' => 'محمد علي'])
            ->assertOk()->assertJsonPath('data.name', 'محمد علي')->assertJsonPath('data.is_active', true);

        $serviceTypeId = ServiceType::query()->withoutGlobalScopes()->value('id');
        $serviceId = $this->postJson('/api/v1/client-services', [
            'client_id' => $clientId,
            'service_type_id' => $serviceTypeId,
            'amount' => 500,
            'currency_id' => $this->ils->id,
            'service_date' => '2026-10-01',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/client-services/{$serviceId}", [
            'client_id' => $clientId,
            'service_type_id' => $serviceTypeId,
            'amount' => 600,
            'currency_id' => $this->ils->id,
            'service_date' => '01\\10\\2026',
        ])->assertOk()->assertJsonPath('data.amount', '600.00');

        $this->getJson("/api/v1/client-services/{$serviceId}")->assertOk();
        $this->getJson('/api/v1/client-services?client_id='.$clientId)->assertOk()->assertJsonPath('total', 1);

        $paymentId = $this->postJson('/api/v1/payments/incoming', [
            'party_type' => 'client',
            'party_id' => $clientId,
            'fund_id' => $this->business->id,
            'payment_method_id' => $this->cash->id,
            'currency_id' => $this->ils->id,
            'amount' => 200,
            'occurred_on' => '2026-10-02',
        ])->assertCreated()->assertJsonPath('data.name', 'شركة النور')->json('data.id');

        $this->putJson("/api/v1/payments/{$paymentId}", [
            'party_type' => 'client',
            'party_id' => $clientId,
            'fund_id' => $this->business->id,
            'payment_method_id' => $this->cash->id,
            'currency_id' => $this->ils->id,
            'amount' => 250,
            'occurred_on' => '2026-10-02',
        ])->assertOk()->assertJsonPath('data.amount', '250.00');

        $this->getJson('/api/v1/payments/incoming')->assertOk()->assertJsonPath('total', 1);
        $this->getJson("/api/v1/payments/{$paymentId}")->assertOk();

        $takeId = $this->postJson('/api/v1/client-goods-takes', [
            'party_type' => 'client',
            'party_id' => $clientId,
            'category' => 'goods',
            'amount' => 50,
            'currency_id' => $this->ils->id,
            'taken_on' => '2026-10-03',
        ])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/client-goods-takes/{$takeId}", [
            'category' => 'other',
            'title' => 'خصم خاص',
            'amount' => 40,
            'currency_id' => $this->ils->id,
            'taken_on' => '2026-10-03',
        ])->assertOk()->assertJsonPath('data.title', 'خصم خاص');

        $memoId = $this->postJson('/api/v1/balance-memos', [
            'party_type' => 'client',
            'party_id' => $clientId,
            'reason' => 'opening',
            'direction' => 'owed_to_us',
            'amount' => 100,
            'currency_id' => $this->ils->id,
            'memo_date' => '2026-01-01',
        ])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/balance-memos/{$memoId}", [
            'reason' => 'opening',
            'direction' => 'owed_to_us',
            'amount' => 90,
            'currency_id' => $this->ils->id,
            'memo_date' => '2026-01-01',
        ])->assertOk()->assertJsonPath('data.amount', '90.00');

        // 600 billed - 250 paid - 40 goods + 90 memo = 400
        $this->getJson('/api/v1/clients')->assertOk()->assertJsonPath('data.0.outstanding.ILS', '400.00');
        $this->getJson("/api/v1/clients/{$clientId}")->assertOk()->assertJsonStructure(['data' => ['client', 'summaries', 'timeline']]);
        $this->getJson("/api/v1/clients/{$clientId}/unpaid-services")->assertOk();

        $pdf = $this->get("/api/v1/clients/{$clientId}/export-pdf");
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        // No delete anywhere.
        $this->deleteJson("/api/v1/clients/{$clientId}")->assertStatus(405);
        $this->deleteJson("/api/v1/payments/{$paymentId}")->assertStatus(405);
        $this->deleteJson("/api/v1/client-services/{$serviceId}")->assertStatus(405);
        $this->assertDatabaseHas('clients', ['id' => $clientId, 'is_active' => true]);
    }

    public function test_balance_transfer_between_parties(): void
    {
        Sanctum::actingAs($this->user);
        $a = $this->postJson('/api/v1/clients', ['name' => 'أ'])->json('data.id');
        $b = $this->postJson('/api/v1/persons', ['name' => 'ب'])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/balance-memos/transfer', [
            'party_type' => 'client',
            'party_id' => $a,
            'target_party_type' => 'client',
            'target_party_id' => $a,
            'direction' => 'owed_to_us',
            'amount' => 10,
            'currency_id' => $this->ils->id,
            'memo_date' => '2026-10-01',
        ])->assertStatus(422)->assertJsonValidationErrors('target_party_key');

        $this->postJson('/api/v1/balance-memos/transfer', [
            'party_type' => 'client',
            'party_id' => $a,
            'target_party_type' => 'person',
            'target_party_id' => $b,
            'direction' => 'owed_to_us',
            'amount' => 10,
            'currency_id' => $this->ils->id,
            'memo_date' => '2026-10-01',
        ])->assertCreated();

        $this->getJson('/api/v1/balance-memos')->assertOk()->assertJsonPath('total', 2);
    }

    public function test_persons_vendors_charges_and_service_types(): void
    {
        Sanctum::actingAs($this->user);

        $personId = $this->postJson('/api/v1/persons', ['name' => 'خالد', 'relationship' => 'أخ'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/persons/{$personId}", ['name' => 'خالد أحمد'])->assertOk()->assertJsonPath('data.is_active', true);
        $this->getJson("/api/v1/persons/{$personId}")->assertOk()->assertJsonStructure(['data' => ['member', 'totals']]);
        $this->getJson('/api/v1/persons')->assertOk()->assertJsonPath('total', 1);

        // A person can only be paid from the personal (family) fund, same as the CP.
        $this->postJson('/api/v1/payments/outgoing', [
            'party_type' => 'person', 'party_id' => $personId,
            'fund_id' => $this->business->id, 'payment_method_id' => $this->cash->id,
            'currency_id' => $this->ils->id, 'amount' => 10, 'occurred_on' => '2026-10-01',
        ])->assertStatus(422)->assertJsonValidationErrors('party_key');

        $workerId = $this->postJson('/api/v1/workers', ['name' => 'سامي', 'job_title' => 'مصمم'])->assertCreated()
            ->assertJsonPath('data.type', 'worker')->json('data.id');
        $supplierId = $this->postJson('/api/v1/suppliers', ['name' => 'مطبعة', 'work_description' => 'طباعة'])->assertCreated()
            ->assertJsonPath('data.type', 'supplier')->json('data.id');
        $this->getJson("/api/v1/suppliers/{$workerId}")->assertNotFound();
        $this->putJson("/api/v1/workers/{$workerId}", ['name' => 'سامي خليل'])->assertOk()->assertJsonPath('data.is_active', true);
        $this->getJson('/api/v1/workers')->assertOk()->assertJsonPath('total', 1);

        $chargeId = $this->postJson('/api/v1/vendor-charges', [
            'vendor_id' => $supplierId, 'title' => 'طباعة بروشور', 'amount' => 300,
            'currency_id' => $this->ils->id, 'charge_date' => '2026-10-01',
        ])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/vendor-charges/{$chargeId}", [
            'title' => 'طباعة بروشور', 'amount' => 350,
            'currency_id' => $this->ils->id, 'charge_date' => '2026-10-01',
        ])->assertOk()->assertJsonPath('data.amount', '350.00');
        $this->getJson("/api/v1/suppliers/{$supplierId}")->assertOk()->assertJsonPath('data.totals.0.billed', '350.00');
        $this->getJson('/api/v1/vendor-charges?vendor_id='.$supplierId)->assertOk()->assertJsonPath('total', 1);

        $typeId = $this->postJson('/api/v1/service-types', ['name' => 'تصوير'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/service-types/{$typeId}", ['name' => 'تصوير منتجات'])->assertOk();
        $this->getJson("/api/v1/service-types/{$typeId}")->assertOk()->assertJsonPath('data.name', 'تصوير منتجات');

        $this->assertSame('application/pdf', $this->get("/api/v1/workers/{$workerId}/export-pdf")->assertOk()->headers->get('Content-Type'));
        $this->assertSame('application/pdf', $this->get("/api/v1/persons/{$personId}/export-pdf")->assertOk()->headers->get('Content-Type'));
        $this->assertSame('application/pdf', $this->get('/api/v1/reports/export-pdf')->assertOk()->headers->get('Content-Type'));
    }

    public function test_opening_balance_transfer_and_reverse(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/balances/opening', [
            'fund_id' => $this->business->id, 'payment_method_id' => $this->cash->id,
            'currency_id' => $this->ils->id, 'amount' => 1000, 'occurred_on' => '2026-10-01',
        ])->assertCreated();

        $this->postJson('/api/v1/transfers', [
            'fund_id' => $this->business->id, 'from_payment_method_id' => $this->cash->id,
            'to_payment_method_id' => $this->bank->id, 'amount' => 5000,
            'currency_id' => $this->ils->id, 'transfer_date' => '2026-10-02',
        ])->assertStatus(422);

        $transferId = $this->postJson('/api/v1/transfers', [
            'fund_id' => $this->business->id, 'from_payment_method_id' => $this->cash->id,
            'to_payment_method_id' => $this->bank->id, 'amount' => 400,
            'currency_id' => $this->ils->id, 'transfer_date' => '2026-10-02',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/transfers')->assertOk()->assertJsonPath('total', 1);
        $this->getJson("/api/v1/transfers/{$transferId}")->assertOk();
        $this->postJson("/api/v1/transfers/{$transferId}/reverse")->assertOk();
        $this->postJson("/api/v1/transfers/{$transferId}/reverse")->assertStatus(422);
        $this->getJson('/api/v1/transfers')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_users_and_roles(): void
    {
        Sanctum::actingAs($this->user);
        $roleId = Role::query()->value('id');

        $userId = $this->postJson('/api/v1/users', [
            'name' => 'محاسب', 'email' => 'acc@example.com', 'password' => 'secret123',
            'password_confirmation' => 'secret123', 'role_id' => $roleId,
        ])->assertCreated()->assertJsonPath('data.tenant_id', $this->user->tenant_id)->json('data.id');
        $this->putJson("/api/v1/users/{$userId}", [
            'name' => 'محاسب أول', 'email' => 'acc@example.com', 'role_id' => $roleId,
        ])->assertOk()->assertJsonPath('data.is_active', true);
        $this->getJson('/api/v1/users')->assertOk();
        $this->assertArrayNotHasKey('password', $this->getJson("/api/v1/users/{$userId}")->assertOk()->json('data'));

        $permissionIds = $this->getJson('/api/v1/permissions')->assertOk()->json('data.*.id');
        $newRole = $this->postJson('/api/v1/roles', [
            'name' => 'مدقق', 'slug' => 'auditor', 'permissions' => $permissionIds,
        ])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/roles/{$newRole}", ['name' => 'مدقق مالي', 'slug' => 'auditor', 'permissions' => []])->assertOk();
        $this->getJson('/api/v1/roles')->assertOk();
    }

    public function test_permissions_are_enforced(): void
    {
        $role = Role::query()->create(['name' => 'بلا صلاحيات', 'slug' => 'none']);
        $limited = User::query()->create([
            'name' => 'محدود', 'email' => 'limited@example.com', 'password' => 'password',
            'role_id' => $role->id, 'tenant_id' => $this->user->tenant_id, 'is_active' => true,
        ]);
        Sanctum::actingAs($limited);

        $this->getJson('/api/v1/clients')->assertForbidden();
        $this->getJson('/api/v1/payments/incoming')->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_other_tenant_records_are_invisible(): void
    {
        $other = Tenant::query()->create(['name' => 'نسخة أخرى', 'slug' => 'other', 'is_active' => true, 'business_enabled' => true]);
        TenantContext::set($other->id);
        $foreign = Client::query()->create(['name' => 'زبون نسخة أخرى', 'is_active' => true]);
        TenantContext::clear();

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/clients/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/clients/{$foreign->id}", ['name' => 'اختراق'])->assertNotFound();
        $this->getJson('/api/v1/clients')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonCount(0, 'clients');
        $this->assertDatabaseHas('clients', ['id' => $foreign->id, 'name' => 'زبون نسخة أخرى']);
    }

    public function test_business_module_disabled_blocks_business_routes(): void
    {
        $this->user->tenant->update(['business_enabled' => false]);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/clients')->assertForbidden();
        $this->getJson('/api/v1/persons')->assertOk();
    }

    public function test_client_excel_import_preview_then_confirm(): void
    {
        Sanctum::actingAs($this->user);

        $token = $this->post('/api/v1/clients/import/preview', ['files' => [$this->workbookUpload('مخبز السعادة.xlsx')]])
            ->assertOk()->assertJsonPath('drafts.0.client_name', 'مخبز السعادة')->json('token');

        $this->postJson('/api/v1/clients/import', [
            'token' => $token,
            'clients' => [['company_name' => 'مجموعة السعادة']],
        ])->assertOk()->assertJsonPath('imported_clients', 1)->assertJsonPath('imported_services', 2);

        $this->postJson('/api/v1/clients/import', ['token' => $token])->assertStatus(422);
        $this->assertSame('مجموعة السعادة', Client::query()->withoutGlobalScopes()->where('name', 'مخبز السعادة')->value('company_name'));
    }

    protected function workbookUpload(string $name): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('كشف حساب');
        $spreadsheet->getActiveSheet()->fromArray([
            ['نوع الخدمة', 'الشهر'],
            ['إعلان ممول', 'يناير 2025'],
            ['تصميم إعلان', 'فبراير 2025'],
        ]);
        $spreadsheet->createSheet()->setTitle('دفعات')->fromArray([
            ['التاريخ', 'نوع العملة', 'المجموع بالشيكل', 'الملاحظات'],
            ['2025-01-25', 'شيكل', 500, 'جوال باي'],
        ]);
        $spreadsheet->createSheet()->setTitle('المجموع')->fromArray([
            ['الوصف', 'المجموع'],
            ['إعلان ممول', 500],
            ['تصميم إعلان', 300],
            ['المجموع', 800],
        ]);
        $spreadsheet->createSheet()->setTitle('إعلان ممول')->fromArray([['التاريخ', 'المبلغ'], ['2025-01-10', 500]]);
        $spreadsheet->createSheet()->setTitle('تصميم إعلان')->fromArray([['التاريخ', 'المبلغ'], ['2025-02-08', 300]]);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'accmoh-api-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
