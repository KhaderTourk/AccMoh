<?php

namespace Tests\Feature\Finance;

use App\Enums\VendorType;
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

class PartyPagesTest extends TestCase
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
        TenantContext::set((int) $this->user->tenant_id);
        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    protected function htmlPdf(): void
    {
        $this->app->instance(\App\Services\Export\PdfExporter::class, new class extends \App\Services\Export\PdfExporter
        {
            public function download(string $view, array $data, string $filename): \Illuminate\Http\Response
            {
                return response(view($view, $data)->render());
            }
        });
    }

    public function test_worker_salary_charge_payment_and_advance(): void
    {
        $this->actingAs($this->user)->post(route('cp.workers.store'), [
            'name' => 'موظف الراتب', 'detail' => 'مصمم', 'salary_amount' => 2000, 'salary_from' => '1/1/2026', 'return' => 'list',
        ])->assertRedirect();
        $worker = Vendor::query()->where('name', 'موظف الراتب')->firstOrFail();
        $this->assertSame('2000.00', $worker->activeSalary());
        $this->assertSame('مصمم', $worker->job_title);
        $this->assertTrue($worker->is_active);

        $this->actingAs($this->user)->get(route('cp.workers.show', $worker))
            ->assertOk()->assertSee('"salary":2000', false)->assertSee('سلفة');

        $method = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $fund = Fund::business();
        // Money in the drawer so the outgoing payments are allowed.
        TenantContext::set((int) $this->user->tenant_id);
        $client = Client::query()->create(['name' => 'زبون التمويل', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('cp.payments.store', ['incoming']), [
            'party_key' => 'client:'.$client->id, 'fund_id' => $fund->id, 'payment_method_id' => $method->id,
            'currency_id' => $this->ils->id, 'amount' => 5000, 'occurred_on' => '1/10/2026',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->actingAs($this->user)->post(route('cp.vendor-charges.store'), [
            'vendor_id' => $worker->id, 'title' => 'راتب شهر 10/2026', 'currency_id' => $this->ils->id, 'amount' => 2000, 'charge_date' => '31/10/2026',
        ])->assertRedirect(route('cp.workers.show', $worker));
        $this->actingAs($this->user)->post(route('cp.payments.store', ['outgoing']), [
            'party_key' => 'vendor:'.$worker->id, 'fund_id' => $fund->id, 'payment_method_id' => $method->id,
            'currency_id' => $this->ils->id, 'amount' => 500, 'occurred_on' => '5/10/2026', 'kind' => 'advance',
        ])->assertRedirect(route('cp.workers.show', $worker));
        $this->assertSame('1500.00', $worker->fresh()->outstandingAmount($this->ils->id));

        $this->actingAs($this->user)->get(route('cp.workers.show', $worker))
            ->assertOk()->assertSee('"kind":"سلفة"', false)->assertSee('"kind":"راتب"', false);

        $this->htmlPdf();
        $this->actingAs($this->user)->get(route('cp.workers.export-pdf', [$worker, 'type' => 'other']))
            ->assertOk()->assertSee('كشف حركات أخرى')->assertSee('سلفة')->assertDontSee('راتب شهر 10/2026')->assertSee('خلال 15 يوماً');
    }

    public function test_person_transfer_moves_both_accounts_and_pdf_is_personal(): void
    {
        $person = Person::query()->create(['name' => 'شخص التحويل', 'is_active' => true]);
        $supplier = Vendor::query()->create(['name' => 'مورد التحويل', 'type' => VendorType::Supplier, 'is_active' => true]);

        $this->actingAs($this->user)->get(route('cp.persons.show', $person))
            ->assertOk()->assertSee('أعطيته')->assertSee('"key":"vendor:'.$supplier->id.'"', false);

        // «من حسابه لحساب تاني»: 300 he owed me now sits on the supplier.
        $this->actingAs($this->user)->post(route('cp.balance-memos.transfer.store'), [
            'source_party_key' => 'person:'.$person->id, 'target_party_key' => 'vendor:'.$supplier->id,
            'direction' => 'owed_to_us', 'amount' => 300, 'currency_id' => $this->ils->id, 'memo_date' => '1/10/2026',
        ])->assertRedirect();

        $this->assertSame('-300.00', $person->fresh()->netAmount($this->ils->id));
        $this->assertSame('-300.00', $supplier->fresh()->outstandingAmount($this->ils->id));

        $this->actingAs($this->user)->get(route('cp.persons.index'))->assertOk()->assertSee('"name":"شخص التحويل"', false);

        $this->htmlPdf();
        $this->actingAs($this->user)->get(route('cp.persons.export-pdf', $person))
            ->assertOk()->assertSee('كشف حساب شخصي')->assertSee('تحويل رصيد')->assertDontSee('خلال 15 يوماً')->assertDontSee('ختم الشركة');
    }
}
