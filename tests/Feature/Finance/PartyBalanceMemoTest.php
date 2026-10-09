<?php

namespace Tests\Feature\Finance;

use App\Enums\BalanceMemoDirection;
use App\Enums\BalanceMemoReason;
use App\Enums\PaymentDirection;
use App\Enums\VendorType;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\PartyBalanceMemo;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Finance\BalanceService;
use App\Services\Finance\CashPaymentService;
use App\Services\Finance\PartyBalanceMemoService;
use App\Support\Money;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyBalanceMemoTest extends TestCase
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

    public function test_person_debt_does_not_move_the_drawer_until_it_is_collected(): void
    {
        $person = Person::query()->create(['name' => 'خالد', 'is_active' => true]);
        $ledgerBefore = LedgerEntry::query()->count();
        $cashBefore = app(BalanceService::class)->totalCash($this->ils->id);

        app(PartyBalanceMemoService::class)->create([
            'party_type' => 'person',
            'party_id' => $person->id,
            'direction' => BalanceMemoDirection::OwedToUs,
            'reason' => BalanceMemoReason::Opening,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'memo_date' => '2024-03-01',
        ]);

        $this->assertSame($ledgerBefore, LedgerEntry::query()->count());
        $this->assertSame($cashBefore, app(BalanceService::class)->totalCash($this->ils->id));
        $this->assertSame('1000.00', $person->fresh()->netAmount($this->ils->id));

        $cash = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        app(CashPaymentService::class)->record([
            'direction' => PaymentDirection::Incoming,
            'party_type' => 'person',
            'party_id' => $person->id,
            'amount' => 1000,
            'currency_id' => $this->ils->id,
            'payment_method_id' => $cash->id,
            'fund_id' => Fund::family()->id,
            'occurred_on' => '2026-10-01',
        ]);

        $this->assertSame('0.00', $person->fresh()->netAmount($this->ils->id));
        $this->assertSame(Money::add($cashBefore, '1000.00'), app(BalanceService::class)->totalCash($this->ils->id));
    }

    public function test_transfer_between_two_persons_keeps_debt_and_drawer(): void
    {
        $source = Person::query()->create(['name' => 'أحمد', 'is_active' => true]);
        $target = Person::query()->create(['name' => 'سامي', 'is_active' => true]);
        $service = app(PartyBalanceMemoService::class);

        $service->create([
            'party_type' => 'person',
            'party_id' => $source->id,
            'direction' => BalanceMemoDirection::OwedToUs,
            'reason' => BalanceMemoReason::Opening,
            'amount' => 800,
            'currency_id' => $this->ils->id,
            'memo_date' => '2025-01-01',
        ]);

        $ledgerBefore = LedgerEntry::query()->count();
        $cashBefore = app(BalanceService::class)->totalCash($this->ils->id);

        $leg = $service->transfer([
            'party_type' => 'person',
            'party_id' => $source->id,
            'target_party_type' => 'person',
            'target_party_id' => $target->id,
            'direction' => BalanceMemoDirection::OwedToUs,
            'amount' => 800,
            'currency_id' => $this->ils->id,
            'memo_date' => '2026-01-01',
        ]);

        $this->assertSame('0.00', $source->fresh()->netAmount($this->ils->id));
        $this->assertSame('800.00', $target->fresh()->netAmount($this->ils->id));
        $this->assertSame('800.00', Money::add(
            $source->fresh()->netAmount($this->ils->id),
            $target->fresh()->netAmount($this->ils->id)
        ));
        $this->assertSame($ledgerBefore, LedgerEntry::query()->count());
        $this->assertSame($cashBefore, app(BalanceService::class)->totalCash($this->ils->id));

        $service->delete($leg);

        $this->assertSame(0, PartyBalanceMemo::query()->where('group_id', $leg->group_id)->count());
        $this->assertSame(2, PartyBalanceMemo::onlyTrashed()->where('group_id', $leg->group_id)->count());
        $this->assertSame('800.00', $source->fresh()->netAmount($this->ils->id));
        $this->assertSame('0.00', $target->fresh()->netAmount($this->ils->id));
    }

    public function test_client_and_vendor_memos_change_balances_without_cash(): void
    {
        $client = Client::query()->create(['name' => 'شركة النور', 'is_active' => true]);
        $vendor = Vendor::query()->create([
            'name' => 'مورد الورق',
            'type' => VendorType::Supplier,
            'is_active' => true,
        ]);
        $ledgerBefore = LedgerEntry::query()->count();

        app(PartyBalanceMemoService::class)->create([
            'party_type' => 'client',
            'party_id' => $client->id,
            'direction' => BalanceMemoDirection::OwedToUs,
            'reason' => BalanceMemoReason::YearRollover,
            'amount' => 250,
            'currency_id' => $this->ils->id,
            'memo_date' => '2026-01-01',
        ]);
        app(PartyBalanceMemoService::class)->create([
            'party_type' => 'vendor',
            'party_id' => $vendor->id,
            'direction' => BalanceMemoDirection::OwedByUs,
            'reason' => BalanceMemoReason::Opening,
            'amount' => 90,
            'currency_id' => $this->ils->id,
            'memo_date' => '2026-01-01',
        ]);

        $this->assertSame('250.00', $client->fresh()->outstandingAmount($this->ils->id));
        $this->assertSame('250.00', $client->fresh()->openingBalance($this->ils->id, '2026-02-01'));
        $this->assertSame('90.00', $vendor->fresh()->outstandingAmount($this->ils->id));
        $this->assertSame($ledgerBefore, LedgerEntry::query()->count());

        $this->actingAs($this->user)
            ->get(route('cp.clients.show', $client))
            ->assertOk()
            ->assertSee('رصيد مرحّل')
            ->assertSee('ترحيل سنة');
        $this->actingAs($this->user)
            ->get(route('cp.suppliers.show', $vendor))
            ->assertOk()
            ->assertSee('رصيد مرحّل')
            ->assertSee('رصيد افتتاحي');
    }

    public function test_pages_record_a_memo_and_a_paired_transfer(): void
    {
        $source = Person::query()->create(['name' => 'ليلى', 'is_active' => true]);
        $target = Person::query()->create(['name' => 'هدى', 'is_active' => true]);

        $this->actingAs($this->user)
            ->get(route('cp.balance-memos.create', ['person_id' => $source->id]))
            ->assertOk()
            ->assertSee('رصيد مرحّل');

        $this->actingAs($this->user)
            ->post(route('cp.balance-memos.store'), [
                'party_key' => 'person:'.$source->id,
                'direction' => BalanceMemoDirection::OwedToUs->value,
                'reason' => BalanceMemoReason::Opening->value,
                'amount' => 300,
                'currency_id' => $this->ils->id,
                'memo_date' => '01\\01\\2025',
            ])
            ->assertRedirect(route('cp.persons.show', $source));

        $this->assertSame('300.00', $source->fresh()->netAmount($this->ils->id));

        $this->actingAs($this->user)
            ->post(route('cp.balance-memos.transfer.store'), [
                'source_party_key' => 'person:'.$source->id,
                'target_party_key' => 'person:'.$target->id,
                'direction' => BalanceMemoDirection::OwedToUs->value,
                'amount' => 300,
                'currency_id' => $this->ils->id,
                'memo_date' => '2026-01-01',
            ])
            ->assertRedirect(route('cp.persons.show', $source));

        $this->assertSame('0.00', $source->fresh()->netAmount($this->ils->id));
        $this->assertSame('300.00', $target->fresh()->netAmount($this->ils->id));
        $this->get(route('cp.persons.show', $source))->assertOk()->assertSee('ترحيل من حساب');
    }
}
