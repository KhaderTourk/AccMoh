<?php

namespace Tests\Feature\Finance;

use App\Enums\VendorType;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashPaymentFormTest extends TestCase
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

    public function test_roles_nav_item_is_hidden(): void
    {
        $this->actingAs($this->user)
            ->get(route('cp.dashboard'))
            ->assertOk()
            ->assertSee('المستخدمون')
            ->assertDontSee('الأدوار والصلاحيات');
    }

    public function test_payment_create_renders_party_dropdown(): void
    {
        TenantContext::set((int) $this->user->tenant_id);
        Client::query()->create(['name' => 'زبون تجريبي', 'is_active' => true]);
        Person::query()->create(['name' => 'شخص تجريبي', 'is_active' => true]);
        Vendor::query()->create(['name' => 'موظف تجريبي', 'type' => VendorType::Worker, 'is_active' => true]);
        Vendor::query()->create(['name' => 'مورد تجريبي', 'type' => VendorType::Supplier, 'is_active' => true]);
        TenantContext::clear();

        $family = Fund::withoutGlobalScopes()
            ->where('tenant_id', $this->user->tenant_id)
            ->where('slug', 'family')
            ->firstOrFail();

        $this->actingAs($this->user)
            ->get('/cp/payments/incoming/create?fund_id='.$family->id)
            ->assertOk()
            ->assertSee('اختر الاسم')
            ->assertSee('الزبائن')
            ->assertSee('الأشخاص')
            ->assertSee('الموظفون')
            ->assertSee('الموردون')
            ->assertSee('زبون تجريبي')
            ->assertSee('شخص تجريبي')
            ->assertSee('موظف تجريبي')
            ->assertSee('مورد تجريبي')
            ->assertSee('party_key');

        $this->actingAs($this->user)
            ->get('/cp/payments/outgoing/create?fund_id='.$family->id)
            ->assertOk()
            ->assertSee('اختر الاسم')
            ->assertSee('party_key');
    }

    public function test_payment_store_requires_party_and_uses_party_name(): void
    {
        TenantContext::set((int) $this->user->tenant_id);
        $person = Person::query()->create(['name' => 'أحمد', 'is_active' => true]);
        TenantContext::clear();

        $family = Fund::withoutGlobalScopes()
            ->where('tenant_id', $this->user->tenant_id)
            ->where('slug', 'family')
            ->firstOrFail();
        $ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();

        $payload = [
            'occurred_on' => now()->toDateString(),
            'fund_id' => $family->id,
            'payment_method_id' => $cash->id,
            'currency_id' => $ils->id,
            'amount' => 100,
        ];

        $this->actingAs($this->user)
            ->from('/cp/payments/incoming/create?fund_id='.$family->id)
            ->post(route('cp.payments.store', 'incoming'), $payload)
            ->assertSessionHasErrors('party_key');

        $this->actingAs($this->user)
            ->post(route('cp.payments.store', 'incoming'), $payload + [
                'party_key' => 'person:'.$person->id,
            ])
            ->assertRedirect(route('cp.persons.show', $person));

        $payment = CashPayment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame('أحمد', $payment->name);
        $this->assertSame('person', $payment->party_type);
        $this->assertSame($person->id, $payment->party_id);
    }

    public function test_person_cannot_be_saved_on_business_fund(): void
    {
        TenantContext::set((int) $this->user->tenant_id);
        $person = Person::query()->create(['name' => 'أحمد', 'is_active' => true]);
        TenantContext::clear();

        $business = Fund::withoutGlobalScopes()
            ->where('tenant_id', $this->user->tenant_id)
            ->where('slug', 'business')
            ->firstOrFail();
        $ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();

        $this->actingAs($this->user)
            ->from('/cp/payments/incoming/create?fund_id='.$business->id)
            ->post(route('cp.payments.store', 'incoming'), [
                'occurred_on' => now()->toDateString(),
                'fund_id' => $business->id,
                'payment_method_id' => $cash->id,
                'currency_id' => $ils->id,
                'amount' => 100,
                'party_key' => 'person:'.$person->id,
            ])
            ->assertSessionHasErrors('party_key');
    }
}
