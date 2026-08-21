<?php

namespace Tests\Feature\Finance;

use App\Enums\LoanDirection;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FamilyMember;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use App\Services\Finance\BalanceService;
use App\Services\Finance\ClientPaymentService;
use App\Services\Finance\ClientWorkService;
use App\Services\Finance\FamilyLoanService;
use App\Services\Finance\FundTransferService;
use App\Services\Finance\LoanRepaymentService;
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

    public function test_scenario_1_borrow_1000_ils_cash_from_ahmed(): void
    {
        $ahmed = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);

        app(FamilyLoanService::class)->create([
            'family_member_id' => $ahmed->id,
            'direction' => LoanDirection::Borrowed,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'loan_date' => '2026-08-20',
            'notes' => 'قرض شخصي',
        ]);

        $balances = app(BalanceService::class);
        $this->assertSame('1000.00', $balances->cash($this->family->id, $this->cash->id, $this->ils->id));
        $this->assertSame('1000.00', $ahmed->fresh()->iOweAmount($this->ils->id));
    }

    public function test_scenario_2_repay_ahmed_400_from_bank_after_transfer(): void
    {
        $ahmed = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);
        $loanService = app(FamilyLoanService::class);
        $balances = app(BalanceService::class);

        $loan = $loanService->create([
            'family_member_id' => $ahmed->id,
            'direction' => LoanDirection::Borrowed,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'loan_date' => '2026-08-20',
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

        app(LoanRepaymentService::class)->repay([
            'family_member_id' => $ahmed->id,
            'direction' => LoanDirection::Borrowed,
            'amount' => 400,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->bank->id,
            'repayment_date' => '2026-08-22',
        ], [
            ['family_loan_id' => $loan->id, 'amount' => 400],
        ]);

        $this->assertSame('600.00', $balances->cash($this->family->id, $this->cash->id, $this->ils->id));
        $this->assertSame('0.00', $balances->cash($this->family->id, $this->bank->id, $this->ils->id));
        $this->assertSame('600.00', $loan->fresh()->remainingAmount());
        $this->assertSame('600.00', $ahmed->fresh()->iOweAmount($this->ils->id));
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
        $service = app(ClientWorkService::class)->create([
            'client_id' => $client->id,
            'title' => 'إعلان ممول',
            'amount' => 500,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);

        app(ClientPaymentService::class)->receive([
            'client_id' => $client->id,
            'amount' => 200,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->palpay->id,
            'payer_name' => 'محمد',
            'payment_date' => '2026-08-21',
        ], [
            ['client_service_id' => $service->id, 'amount' => 200],
        ]);

        $balances = app(BalanceService::class);
        $this->assertSame('200.00', $balances->cash($this->business->id, $this->palpay->id, $this->usd->id));
        $this->assertSame('300.00', $client->fresh()->outstandingAmount($this->usd->id));
    }

    public function test_scenario_5_payment_allocated_across_two_services(): void
    {
        $client = Client::query()->create(['name' => 'محمد', 'is_active' => true]);
        $work = app(ClientWorkService::class);

        $ad = $work->create([
            'client_id' => $client->id,
            'title' => 'إعلان ممول',
            'amount' => 300,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);
        $design = $work->create([
            'client_id' => $client->id,
            'title' => 'تصميم',
            'amount' => 100,
            'currency_id' => $this->usd->id,
            'service_date' => '2026-08-20',
        ]);

        $payment = app(ClientPaymentService::class)->receive([
            'client_id' => $client->id,
            'amount' => 300,
            'currency_id' => $this->usd->id,
            'payment_method_id' => $this->bank->id,
            'payer_name' => 'محمد',
            'payment_date' => '2026-08-22',
        ], [
            ['client_service_id' => $ad->id, 'amount' => 200],
            ['client_service_id' => $design->id, 'amount' => 100],
        ]);

        $this->assertSame('300.00', Money::of($payment->amount));
        $this->assertSame('200.00', $ad->fresh()->paidAmount());
        $this->assertSame('100.00', $ad->fresh()->remainingAmount());
        $this->assertSame('100.00', $design->fresh()->paidAmount());
        $this->assertSame('0.00', $design->fresh()->remainingAmount());
        $this->assertSame('100.00', $client->fresh()->outstandingAmount($this->usd->id));
    }

    public function test_scenario_6_transfer_cash_to_bank_preserves_fund_total(): void
    {
        $ahmed = FamilyMember::query()->create(['name' => 'أحمد', 'is_active' => true]);
        app(FamilyLoanService::class)->create([
            'family_member_id' => $ahmed->id,
            'direction' => LoanDirection::Borrowed,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $this->cash->id,
            'loan_date' => '2026-08-20',
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
}
