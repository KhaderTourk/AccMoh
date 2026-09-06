<?php

namespace Tests\Feature\Finance;

use App\Enums\PaymentDirection;
use App\Enums\VendorType;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FamilyMember;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\ServiceType;
use App\Models\Vendor;
use App\Services\Finance\AdjustmentService;
use App\Services\Finance\BalanceService;
use App\Services\Finance\CashPaymentService;
use App\Services\Finance\ClientWorkService;
use App\Services\Finance\FundTransferService;
use App\Services\Finance\ProfitService;
use App\Services\Finance\VendorChargeService;
use App\Support\Money;
use Database\Seeders\FinanceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected Currency $ils;
    protected Currency $usd;
    protected PaymentMethod $cash;
    protected PaymentMethod $bank;
    protected PaymentMethod $palpay;
    protected Fund $family;
    protected Fund $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FinanceCatalogSeeder::class);

        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $this->usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $this->bank = PaymentMethod::query()->where('slug', 'bank')->firstOrFail();
        $this->palpay = PaymentMethod::query()->where('slug', 'palpay')->firstOrFail();
        $this->family = Fund::family();
        $this->business = Fund::business();
    }

    protected function pay(array $data): CashPayment
    {
        if (isset($data['client_id'])) {
            $data['party_type'] = 'client';
            $data['party_id'] = $data['client_id'];
            $data['name'] = $data['name'] ?? Client::query()->find($data['client_id'])?->name;
        }
        if (isset($data['person_id']) || isset($data['family_member_id'])) {
            $id = $data['person_id'] ?? $data['family_member_id'];
            $data['party_type'] = 'person';
            $data['party_id'] = $id;
            $data['name'] = $data['name'] ?? Person::query()->find($id)?->name;
        }
        if (isset($data['vendor_id'])) {
            $data['party_type'] = 'vendor';
            $data['party_id'] = $data['vendor_id'];
            $data['name'] = $data['name'] ?? Vendor::query()->find($data['vendor_id'])?->name;
        }

        return app(CashPaymentService::class)->record($data);
    }

    public function test_scenario_1_incoming_1000_ils_cash_from_ahmed(): void
    {
        $ahmed = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);

        $this->pay([
            'direction' => PaymentDirection::Incoming,
            'family_member_id' => $ahmed->id,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-20',
            'notes' => 'قرض شخصي',
        ]);

        $balances = app(BalanceService::class);
        $this->assertSame('1000.00', $balances->cash($this->family->id, $this->cash->id, $this->ils->id));
        $this->assertSame('1000.00', $ahmed->fresh()->incomingAmount($this->ils->id));
        $this->assertSame('قرض شخصي', $ahmed->cashPayments()->first()->notes);
    }

    public function test_scenario_2_outgoing_400_from_bank_after_transfer(): void
    {
        $ahmed = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);
        $balances = app(BalanceService::class);

        $this->pay([
            'direction' => PaymentDirection::Incoming,
            'family_member_id' => $ahmed->id,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-20',
        ]);

        app(FundTransferService::class)->transfer([
            'fund_id' => $this->family->id,
            'from_payment_method_id' => $this->cash->id,
            'to_payment_method_id' => $this->bank->id,
            'amount' => 400,
            'currency_id' => $this->ils->id,
            'fee_amount' => 0,
            'transfer_date' => '2026-08-21',
        ]);

        $this->pay([
            'direction' => PaymentDirection::Outgoing,
            'family_member_id' => $ahmed->id,
            'amount' => 400,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->bank->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-22',
        ]);

        $this->assertSame('600.00', $balances->cash($this->family->id, $this->cash->id, $this->ils->id));
        $this->assertSame('0.00', $balances->cash($this->family->id, $this->bank->id, $this->ils->id));
        $this->assertSame('600.00', $ahmed->fresh()->netAmount($this->ils->id));
    }

    public function test_scenario_3_client_service_creates_receivable_not_cash(): void
    {
        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        $type = ServiceType::query()->first();

        app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'service_type_id' => $type->id,
            'title' => 'إعلان ممول',
            'amount' => 500,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);

        $balances = app(BalanceService::class);
        $this->assertSame('0.00', $balances->cash($this->business->id, $this->cash->id, $this->usd->id));
        $this->assertSame('0.00', $balances->cash($this->business->id, $this->palpay->id, $this->usd->id));
        $this->assertSame('500.00', $client->fresh()->outstandingAmount($this->usd->id));
    }

    public function test_scenario_4_client_pays_200_via_palpay(): void
    {
        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'إعلان ممول',
            'amount' => 500,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);

        $this->pay([
            'direction' => PaymentDirection::Incoming,
            'client_id' => $client->id,
            'amount' => 200,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->palpay->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-21',
        ]);

        $balances = app(BalanceService::class);
        $this->assertSame('200.00', $balances->cash($this->business->id, $this->palpay->id, $this->usd->id));
        $this->assertSame('300.00', $client->fresh()->outstandingAmount($this->usd->id));
    }

    public function test_scenario_5_payment_reduces_client_total_not_a_single_service(): void
    {
        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        $work = app(ClientWorkService::class);

        $work->create([
            'client_id' => $client->id,
            'title' => 'إعلان ممول',
            'amount' => 300,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);
        $work->create([
            'client_id' => $client->id,
            'title' => 'تصميم',
            'amount' => 100,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);

        $payment = $this->pay([
            'direction' => PaymentDirection::Incoming,
            'client_id' => $client->id,
            'amount' => 300,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->bank->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-22',
        ]);

        $this->assertSame('300.00', Money::of($payment->amount));
        $this->assertSame('400.00', $client->fresh()->billedAmount($this->usd->id));
        $this->assertSame('300.00', $client->fresh()->paidAmount($this->usd->id));
        $this->assertSame('100.00', $client->fresh()->outstandingAmount($this->usd->id));

        $this->pay([
            'direction' => PaymentDirection::Incoming,
            'client_id' => $client->id,
            'amount' => 150,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->bank->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-23',
        ]);

        $this->assertSame('450.00', $client->fresh()->paidAmount($this->usd->id));
        $this->assertSame('-50.00', $client->fresh()->outstandingAmount($this->usd->id));
    }

    public function test_client_service_can_be_updated_and_deleted_even_when_payments_exceed_billed(): void
    {
        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        $work = app(ClientWorkService::class);
        $service = $work->create([
            'client_id' => $client->id,
            'title' => 'إعلان',
            'amount' => 500,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);

        $this->pay([
            'direction' => PaymentDirection::Incoming,
            'client_id' => $client->id,
            'amount' => 200,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-21',
        ]);

        $updated = $work->update($service, [
            'title' => 'إعلان محدّث',
            'amount' => 100,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);
        $this->assertSame('إعلان محدّث', $updated->title);
        $this->assertSame('-100.00', $client->fresh()->outstandingAmount($this->usd->id));

        $work->delete($updated);
        $this->assertSame('-200.00', $client->fresh()->outstandingAmount($this->usd->id));
    }

    public function test_advance_payment_allowed_without_any_service(): void
    {
        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);

        $payment = $this->pay([
            'direction' => PaymentDirection::Incoming,
            'client_id' => $client->id,
            'amount' => 150,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-21',
        ]);

        $this->assertSame('150.00', Money::of($payment->amount));
        $this->assertSame('0.00', $client->fresh()->billedAmount($this->ils->id));
        $this->assertSame('150.00', $client->fresh()->paidAmount($this->ils->id));
        $this->assertSame('-150.00', $client->fresh()->outstandingAmount($this->ils->id));
    }

    public function test_usd_service_converts_to_ils_total(): void
    {
        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        $service = app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'إعلان',
            'source_amount' => 100,
            'exchange_rate' => '3.65',
            'fx_currency_id' => $this->usd->id,
            'currency_id' => $this->ils->id,
            'service_date' => '2026-08-20',
        ]);

        $this->assertSame('365.00', Money::of($service->amount));
        $this->assertTrue($service->isFx());
        $this->assertSame('365.00', $client->fresh()->outstandingAmount($this->ils->id));
        $this->assertSame('0.00', $client->fresh()->outstandingAmount($this->usd->id));
    }

    public function test_usd_payment_posts_ils_cash(): void
    {
        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        $payment = $this->pay([
            'direction' => PaymentDirection::Incoming,
            'client_id' => $client->id,
            'source_amount' => 50,
            'exchange_rate' => '3.65',
            'fx_currency_id' => $this->usd->id,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-21',
        ]);

        $this->assertSame('182.50', Money::of($payment->amount));
        $this->assertSame('182.50', app(BalanceService::class)->cash($this->business->id, $this->cash->id, $this->ils->id));
        $this->assertSame('0.00', app(BalanceService::class)->cash($this->business->id, $this->cash->id, $this->usd->id));
        $this->assertSame('-182.50', $client->fresh()->outstandingAmount($this->ils->id));
    }

    public function test_scenario_6_transfer_cash_to_bank_preserves_fund_total(): void
    {
        $ahmed = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);
        $this->pay([
            'direction' => PaymentDirection::Incoming,
            'family_member_id' => $ahmed->id,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-20',
        ]);

        $balances = app(BalanceService::class);
        $before = $balances->fundCash($this->family->id, $this->ils->id);

        app(FundTransferService::class)->transfer([
            'fund_id' => $this->family->id,
            'from_payment_method_id' => $this->cash->id,
            'to_payment_method_id' => $this->bank->id,
            'amount' => 500,
            'currency_id' => $this->ils->id,
            'fee_amount' => 0,
            'transfer_date' => '2026-08-21',
        ]);

        $this->assertSame('500.00', $balances->cash($this->family->id, $this->cash->id, $this->ils->id));
        $this->assertSame('500.00', $balances->cash($this->family->id, $this->bank->id, $this->ils->id));
        $this->assertSame($before, $balances->fundCash($this->family->id, $this->ils->id));
    }

    public function test_person_fx_converts_usd_to_ils(): void
    {
        $member = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);

        $payment = $this->pay([
            'direction' => PaymentDirection::Incoming,
            'family_member_id' => $member->id,
            'source_amount' => 100,
            'exchange_rate' => '3.65',
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-20',
        ]);

        $this->assertSame('365.00', Money::of($payment->amount));
        $this->assertTrue($payment->isFx());
        $this->assertSame('365.00', app(BalanceService::class)->cash($this->family->id, $this->cash->id, $this->ils->id));
        $this->assertSame('365.00', $member->fresh()->incomingAmount($this->ils->id));
    }

    public function test_worker_outgoing_and_period_profit(): void
    {
        $client = Client::query()->create(['name' => 'عميل', 'is_active' => true]);
        app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'خدمة',
            'amount' => 800,
            'currency_id' => $this->ils->id,
            'service_date' => '2026-08-10',
        ]);
        $this->pay([
            'direction' => PaymentDirection::Incoming,
            'client_id' => $client->id,
            'amount' => 500,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-15',
        ]);

        $worker = Vendor::query()->create([
            'name' => 'عامل 1',
            'type' => VendorType::Worker,
            'is_active' => true,
        ]);

        $this->pay([
            'direction' => PaymentDirection::Outgoing,
            'vendor_id' => $worker->id,
            'amount' => 100,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-16',
        ]);

        $rows = collect(app(ProfitService::class)->forPeriod('2026-08-01', '2026-08-31'));
        $ilsRow = $rows->first(fn ($r) => $r['currency']->id === $this->ils->id);

        $this->assertNotNull($ilsRow);
        $this->assertSame('500.00', $ilsRow['payments']);
        $this->assertSame('100.00', $ilsRow['work_expenses']);
        $this->assertSame('100.00', $ilsRow['worker_expenses']);
        $this->assertSame('400.00', $ilsRow['net_profit']);
        $this->assertSame('300.00', $ilsRow['outstanding']);
        $this->assertSame('700.00', $ilsRow['gross_profit']);
        $this->assertSame('100.00', $worker->fresh()->paidAmount($this->ils->id));
        $this->assertSame('-100.00', $worker->fresh()->outstandingAmount($this->ils->id));
    }

    public function test_supplier_charge_is_owed_until_paid(): void
    {
        $supplier = Vendor::query()->create([
            'name' => 'مطبعة',
            'type' => VendorType::Supplier,
            'is_active' => true,
        ]);

        app(VendorChargeService::class)->create([
            'vendor_id' => $supplier->id,
            'title' => 'طباعة كروت',
            'amount' => 400,
            'currency_id' => $this->ils->id,
            'charge_date' => '2026-08-10',
        ]);

        $this->assertSame('400.00', $supplier->fresh()->billedAmount($this->ils->id));
        $this->assertSame('400.00', $supplier->fresh()->outstandingAmount($this->ils->id));

        app(AdjustmentService::class)->opening([
            'fund_id' => $this->business->id,
            'payment_method_id' => $this->cash->id,
            'currency_id' => $this->ils->id,
            'amount' => 1000,
            'occurred_on' => '2026-08-01',
        ]);

        $this->pay([
            'direction' => PaymentDirection::Outgoing,
            'vendor_id' => $supplier->id,
            'amount' => 150,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->business->id,
            'occurred_on' => '2026-08-12',
        ]);

        $supplier = $supplier->fresh();
        $this->assertSame('400.00', $supplier->billedAmount($this->ils->id));
        $this->assertSame('150.00', $supplier->paidAmount($this->ils->id));
        $this->assertSame('250.00', $supplier->outstandingAmount($this->ils->id));
    }

    public function test_employee_charge_tracks_dues(): void
    {
        $employee = Vendor::query()->create([
            'name' => 'موظف 1',
            'type' => VendorType::Worker,
            'is_active' => true,
        ]);

        app(VendorChargeService::class)->create([
            'vendor_id' => $employee->id,
            'title' => 'راتب شهر 8',
            'amount' => 2000,
            'currency_id' => $this->ils->id,
            'charge_date' => '2026-08-31',
        ]);

        $this->assertSame('2000.00', $employee->fresh()->outstandingAmount($this->ils->id));
    }

    public function test_outgoing_notes_are_saved_on_payment_and_ledger(): void
    {
        $ahmed = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);
        $this->pay([
            'direction' => PaymentDirection::Incoming,
            'family_member_id' => $ahmed->id,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-20',
        ]);

        $payment = $this->pay([
            'direction' => PaymentDirection::Outgoing,
            'name' => 'خضار',
            'amount' => 50,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-21',
            'notes' => 'من السوق المركزي',
        ]);

        $this->assertSame('من السوق المركزي', $payment->fresh()->notes);
        $this->assertDatabaseHas('ledger_entries', [
            'notes' => 'من السوق المركزي',
        ]);
    }

    public function test_updating_cash_payment_reverses_then_reposts(): void
    {
        $ahmed = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);
        $payment = $this->pay([
            'direction' => PaymentDirection::Incoming,
            'family_member_id' => $ahmed->id,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-20',
        ]);

        $updated = app(CashPaymentService::class)->update($payment, [
            'direction' => PaymentDirection::Incoming,
            'name' => 'أحمد',
            'party_type' => 'person',
            'party_id' => $ahmed->id,
            'amount' => 800,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'fund_id' => $this->family->id,
            'occurred_on' => '2026-08-20',
        ]);

        $this->assertTrue($payment->fresh()->is_reversed);
        $this->assertSame('800.00', Money::of($updated->amount));
        $this->assertSame('800.00', app(BalanceService::class)->cash($this->family->id, $this->cash->id, $this->ils->id));
    }
}
