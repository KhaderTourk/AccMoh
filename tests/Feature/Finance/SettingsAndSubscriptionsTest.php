<?php

namespace Tests\Feature\Finance;

use App\Models\CashPayment;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientSubscription;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\ServiceGroup;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Finance\BalanceService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAndSubscriptionsTest extends TestCase
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
        ServiceType::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        TenantContext::set((int) $this->user->tenant_id);
        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_settings_manage_groups_services_and_methods(): void
    {
        $this->actingAs($this->user)->get(route('cp.settings.index'))->assertOk()->assertSee('الإعدادات');

        $this->actingAs($this->user)->post(route('cp.settings.groups.store'), ['name' => 'تصميم'])->assertRedirect();
        $group = ServiceGroup::query()->where('name', 'تصميم')->firstOrFail();
        $this->actingAs($this->user)->post(route('cp.settings.groups.store'), ['name' => 'تصميم'])->assertSessionHasErrors('name');

        $this->actingAs($this->user)->post(route('cp.settings.services.store'), [
            'name' => 'إدارة صفحة', 'service_group_id' => $group->id, 'billing' => 'monthly', 'is_active' => 1,
        ])->assertRedirect();
        $type = ServiceType::query()->where('name', 'إدارة صفحة')->firstOrFail();
        $this->assertSame('monthly', $type->billing);
        $this->assertSame($group->id, $type->service_group_id);

        // A group with services can't be deleted.
        $this->actingAs($this->user)->delete(route('cp.settings.groups.destroy', $group))->assertSessionHas('error');
        $this->assertNotNull($group->fresh());

        // Renaming a used service renames its old movements; a used service can only be switched off.
        $client = Client::query()->create(['name' => 'زبون الإعدادات', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('cp.client-services.store'), [
            'client_id' => $client->id, 'service_type_id' => $type->id,
            'currency_id' => $this->ils->id, 'amount' => 100, 'service_date' => '1/10/2026',
        ]);
        $this->actingAs($this->user)->put(route('cp.settings.services.update', $type), [
            'name' => 'إدارة حسابات', 'service_group_id' => $group->id, 'billing' => 'monthly', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame('إدارة حسابات', ClientService::query()->where('client_id', $client->id)->value('title'));

        $this->actingAs($this->user)->delete(route('cp.settings.services.destroy', $type))->assertSessionHas('error');
        $this->actingAs($this->user)->post(route('cp.settings.services.toggle', $type));
        $this->assertFalse($type->fresh()->is_active);

        $this->actingAs($this->user)->post(route('cp.settings.methods.store'), ['name' => 'محفظة جديدة'])->assertRedirect();
        $method = PaymentMethod::query()->where('name', 'محفظة جديدة')->firstOrFail();
        $this->actingAs($this->user)->post(route('cp.settings.methods.store'), ['name' => 'محفظة جديدة'])->assertSessionHasErrors('name');
        $this->actingAs($this->user)->delete(route('cp.settings.methods.destroy', $method))->assertRedirect();
        $this->assertNull(PaymentMethod::query()->find($method->id));

        $used = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        CashPayment::query()->create([
            'direction' => 'incoming', 'fund_id' => Fund::query()->first()->id, 'payment_method_id' => $used->id,
            'currency_id' => $this->ils->id, 'amount' => 1, 'name' => 'x', 'occurred_on' => '2026-10-01',
            'ledger_group_id' => 'test', 'is_reversed' => false,
        ]);
        $this->actingAs($this->user)->delete(route('cp.settings.methods.destroy', $used))->assertSessionHas('error');
    }

    public function test_free_title_service_and_refund_change_the_client_balance(): void
    {
        $client = Client::query()->create(['name' => 'زبون الاسترداد', 'is_active' => true]);
        $method = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $fund = Fund::query()->where('slug', 'business')->first() ?? Fund::family();

        $this->actingAs($this->user)->post(route('cp.client-services.store'), [
            'client_id' => $client->id, 'title' => 'تصوير منتج خاص',
            'currency_id' => $this->ils->id, 'amount' => 300, 'service_date' => '2/10/2026',
        ])->assertRedirect(route('cp.clients.show', $client));
        $this->assertSame('تصوير منتج خاص', ClientService::query()->where('client_id', $client->id)->value('title'));

        $this->actingAs($this->user)->post(route('cp.payments.store', ['incoming']), [
            'party_key' => 'client:'.$client->id, 'fund_id' => $fund->id, 'payment_method_id' => $method->id,
            'currency_id' => $this->ils->id, 'amount' => 500, 'occurred_on' => '3/10/2026',
        ]);
        $this->assertSame('-200.00', $client->fresh()->outstandingAmount($this->ils->id));

        $this->actingAs($this->user)->post(route('cp.payments.store', ['outgoing']), [
            'party_key' => 'client:'.$client->id, 'fund_id' => $fund->id, 'payment_method_id' => $method->id,
            'currency_id' => $this->ils->id, 'amount' => 200, 'occurred_on' => '4/10/2026', 'kind' => 'refund',
        ])->assertRedirect(route('cp.clients.show', $client));

        $this->assertSame('0.00', $client->fresh()->outstandingAmount($this->ils->id));
        $this->assertSame('0.00', app(BalanceService::class)->clientReceivables($client->id)[$this->ils->id]);
        $this->actingAs($this->user)->get(route('cp.clients.show', $client))
            ->assertOk()->assertSee('"kind":"استرداد"', false);
    }

    public function test_monthly_subscription_is_confirmed_by_month_and_prorated(): void
    {
        $client = Client::query()->create(['name' => 'زبون الاشتراك', 'is_active' => true]);
        $type = ServiceType::query()->create(['name' => 'استضافة', 'billing' => 'monthly', 'is_active' => true]);

        $this->actingAs($this->user)->post(route('cp.subscriptions.store'), [
            'client_id' => $client->id, 'service_type_id' => $type->id, 'amount' => 310, 'starts_on' => '17/8/2026',
        ])->assertRedirect(route('cp.clients.show', $client));
        $sub = ClientSubscription::query()->firstOrFail();

        // Nothing is billed until a month is confirmed.
        $this->assertSame('0.00', $client->fresh()->outstandingAmount($this->ils->id));
        $this->assertSame('150.00', $sub->suggestedAmount('2026-08')); // 15 of 31 days
        $this->assertSame(['2026-08', '2026-09', '2026-10'], $sub->pendingMonths('2026-10'));

        $this->actingAs($this->user)->get(route('cp.subscriptions.confirm', ['month' => '2026-10']))
            ->assertOk()->assertSee('زبون الاشتراك')->assertSee('150.00');

        $this->actingAs($this->user)->post(route('cp.subscriptions.confirm.store'), [
            'month' => '2026-10',
            'items' => [
                $sub->id.'|2026-08' => ['on' => 1, 'amount' => '150.00'],
                $sub->id.'|2026-09' => ['on' => 1, 'amount' => '310.00'],
                $sub->id.'|2026-10' => ['amount' => '310.00'],
            ],
        ])->assertRedirect();
        $this->assertSame('460.00', $client->fresh()->outstandingAmount($this->ils->id));
        $this->assertSame(['2026-10'], $sub->fresh()->pendingMonths('2026-10'));

        // Confirming the same month twice records it once.
        $this->actingAs($this->user)->post(route('cp.subscriptions.confirm.store'), [
            'items' => [$sub->id.'|2026-09' => ['on' => 1, 'amount' => '310.00']],
        ]);
        $this->assertSame('460.00', $client->fresh()->outstandingAmount($this->ils->id));

        // Stopping mid-month adjusts a recorded last month by days instead of repeating it.
        $this->actingAs($this->user)->post(route('cp.subscriptions.stop', $sub), ['ends_on' => '10/9/2026'])->assertRedirect();
        $this->assertSame('103.33', ClientService::query()->where('period', '2026-09')->value('amount')); // 10 of 30 days
        $this->assertSame([], $sub->fresh()->pendingMonths('2026-10'));

        $this->actingAs($this->user)->delete(route('cp.subscriptions.destroy', $sub))->assertSessionHas('error');
    }

    public function test_mid_month_start_waits_for_next_month_then_bills_days_and_new_month_separately(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00'));
        $client = Client::query()->create(['name' => 'زبون نص شهر', 'is_active' => true]);
        $type = ServiceType::query()->create(['name' => 'إدارة صفحات', 'billing' => 'monthly', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('cp.subscriptions.store'), [
            'client_id' => $client->id, 'service_type_id' => $type->id, 'amount' => 310, 'starts_on' => '15/10/2026',
        ])->assertRedirect();
        $sub = ClientSubscription::query()->firstOrFail();

        // During the first month nothing is due yet; the client page shows the running days.
        $this->assertCount(0, \App\Http\Controllers\Cp\ClientSubscriptionController::pending('2026-10'));
        $this->assertSame(1, $sub->accruing()['days']);
        $this->actingAs($this->user)->post(route('cp.subscriptions.confirm.store'), [
            'items' => [$sub->id.'|2026-10' => ['on' => 1, 'amount' => '310.00']],
        ]);
        $this->assertSame(0, ClientService::query()->count());

        // On the 1st of the next month: October by its 17 days, and a new full invoice for November.
        $this->travelTo(CarbonImmutable::parse('2026-11-01 08:00'));
        $rows = \App\Http\Controllers\Cp\ClientSubscriptionController::pending('2026-11');
        $this->assertSame(['2026-10', '2026-11'], $rows->pluck('period')->all());
        $this->assertSame([17, 31], [$rows[0]['days'], $rows[0]['inMonth']]);
        $this->assertSame('170.00', $rows[0]['amount']);
        $this->assertSame('310.00', $rows[1]['amount']);
        $this->actingAs($this->user)->get(route('cp.dashboard'))->assertSee('فواتير الاشتراكات جاهزة');
        $this->actingAs($this->user)->get(route('cp.subscriptions.confirm'))->assertOk()->assertSee('17 يوم من 31');

        $this->actingAs($this->user)->post(route('cp.subscriptions.confirm.store'), [
            'items' => [
                $sub->id.'|2026-10' => ['on' => 1, 'amount' => '170.00'],
                $sub->id.'|2026-11' => ['on' => 1, 'amount' => '310.00'],
            ],
        ])->assertRedirect();
        $this->assertSame(2, ClientService::query()->where('subscription_id', $sub->id)->count());
        $this->assertStringContainsString('(17 يوم من 31)', ClientService::query()->where('period', '2026-10')->value('title'));
        $this->assertSame('480.00', $client->fresh()->outstandingAmount($this->ils->id));
        $this->assertCount(0, \App\Http\Controllers\Cp\ClientSubscriptionController::pending('2026-11'));
    }

    public function test_client_who_did_not_continue_is_stopped_at_last_month_end(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20'));
        $client = Client::query()->create(['name' => 'زبون وقف', 'is_active' => true]);
        $sub = ClientSubscription::query()->create([
            'client_id' => $client->id, 'title' => 'استضافة', 'amount' => 100, 'currency_id' => $this->ils->id, 'starts_on' => '2026-09-01',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-01'));
        $this->actingAs($this->user)->post(route('cp.subscriptions.stop', $sub), ['ends_on' => '31/10/2026', 'return' => 'confirm'])
            ->assertRedirect(route('cp.subscriptions.confirm'));
        $periods = \App\Http\Controllers\Cp\ClientSubscriptionController::pending('2026-11')->pluck('period')->all();
        $this->assertSame(['2026-09', '2026-10'], $periods);
    }

    public function test_monthly_service_drops_phases_and_can_be_saved_switched_off(): void
    {
        $this->actingAs($this->user)->post(route('cp.settings.services.store'), [
            'name' => 'صيانة شهرية', 'billing' => 'monthly', 'has_phases' => 1, 'default_price' => 200, 'is_active' => 0,
        ])->assertRedirect();
        $t = ServiceType::query()->where('name', 'صيانة شهرية')->firstOrFail();
        $this->assertTrue($t->isMonthly());
        $this->assertFalse($t->has_phases);
        $this->assertFalse($t->is_active);
    }
}
