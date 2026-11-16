<?php

namespace Tests\Feature\Finance;

use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\User;
use App\Services\Finance\BalanceService;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 16/11/2026: a payment in USD moves the USD drawer; the party balance stays in ILS at the entered rate. */
class ForeignDrawerPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Currency $ils;

    protected Currency $usd;

    protected PaymentMethod $bank;

    protected Fund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $this->user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Fund::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        TenantContext::set((int) $this->user->tenant_id);
        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $this->usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->bank = PaymentMethod::query()->where('slug', 'bank')->firstOrFail();
        $this->fund = Fund::business();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    protected function drawer(Currency $currency): string
    {
        TenantContext::set((int) $this->user->tenant_id);
        return app(BalanceService::class)->drawerCash($this->bank->id, $currency->id);
    }

    /** 100 $ from a client into the bank USD drawer at 3.7. */
    protected function fundUsd(): Client
    {
        TenantContext::set((int) $this->user->tenant_id);
        $client = Client::query()->create(['name' => 'زبون دولار', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('cp.payments.store', ['incoming']), [
            'party_key' => 'client:'.$client->id, 'fund_id' => $this->fund->id, 'payment_method_id' => $this->bank->id,
            'currency_id' => $this->usd->id, 'source_amount' => 100, 'exchange_rate' => 3.7, 'occurred_on' => '14/11/2026',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        return $client;
    }

    public function test_usd_payment_moves_usd_drawer_and_ils_balance(): void
    {
        $client = $this->fundUsd();
        $this->assertSame('100.00', $this->drawer($this->usd));
        $this->assertSame('0.00', $this->drawer($this->ils));
        $this->assertSame('-370.00', $client->fresh()->outstandingAmount($this->ils->id));

        TenantContext::set((int) $this->user->tenant_id);
        $person = Person::query()->create(['name' => 'شخص', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('cp.payments.store', ['outgoing']), [
            'party_key' => 'person:'.$person->id, 'fund_id' => Fund::family()->id, 'payment_method_id' => $this->bank->id,
            'currency_id' => $this->usd->id, 'source_amount' => 5, 'exchange_rate' => 3.7, 'occurred_on' => '15/11/2026',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        // 5 $ out of the USD drawer, nothing from the ILS drawer.
        $this->assertSame('95.00', $this->drawer($this->usd));
        $this->assertSame('0.00', $this->drawer($this->ils));
        // The person's account: 18.50 ₪ (5 × 3.7).
        $this->assertSame('18.50', $person->fresh()->netAmount($this->ils->id));
        $payment = CashPayment::query()->latest('id')->firstOrFail();
        $this->assertSame($this->ils->id, (int) $payment->currency_id);
        $this->assertSame('18.50', (string) $payment->amount);

        // Cancelling gives the 5 $ back to the USD drawer.
        $this->actingAs($this->user)->delete(route('cp.payments.destroy', $payment))->assertSessionMissing('error');
        $this->assertSame('100.00', $this->drawer($this->usd));
        $this->assertSame('0.00', $person->fresh()->netAmount($this->ils->id));
    }

    public function test_usd_payment_needs_usd_in_the_drawer(): void
    {
        TenantContext::set((int) $this->user->tenant_id);
        $person = Person::query()->create(['name' => 'شخص', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('cp.payments.store', ['outgoing']), [
            'party_key' => 'person:'.$person->id, 'fund_id' => Fund::family()->id, 'payment_method_id' => $this->bank->id,
            'currency_id' => $this->usd->id, 'source_amount' => 5, 'exchange_rate' => 3.7, 'occurred_on' => '15/11/2026',
        ])->assertSessionHas('error');
        $this->assertSame(0, CashPayment::query()->count());
    }

    public function test_command_moves_old_ils_lines_to_real_currency(): void
    {
        $this->fundUsd();
        // The old behaviour: the drawer line was written in ILS.
        $entry = LedgerEntry::query()->firstOrFail();
        $entry->forceFill(['currency_id' => $this->ils->id, 'amount' => '370.00'])->save();
        $this->assertSame('370.00', $this->drawer($this->ils));

        $this->artisan('drawers:fix-foreign-payments')->assertSuccessful();
        $this->assertSame('370.00', $this->drawer($this->ils), 'report only without --apply');

        $this->artisan('drawers:fix-foreign-payments', ['--apply' => true])->assertSuccessful();
        $this->assertSame('0.00', $this->drawer($this->ils));
        $this->assertSame('100.00', $this->drawer($this->usd));

        $this->artisan('drawers:fix-foreign-payments', ['--apply' => true])->expectsOutputToContain('Fixed 0');
    }
}
