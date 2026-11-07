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

    public function test_monthly_subscription_records_first_month_now_and_uses_30_day_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00'));
        $client = Client::query()->create(['name' => 'زبون الاشتراك', 'is_active' => true]);
        $type = ServiceType::query()->create(['name' => 'استضافة', 'billing' => 'monthly', 'is_active' => true]);

        $this->actingAs($this->user)->post(route('cp.subscriptions.store'), [
            'client_id' => $client->id, 'service_type_id' => $type->id, 'amount' => 300, 'starts_on' => '17/8/2026',
        ])->assertRedirect(route('cp.clients.show', $client));
        $sub = ClientSubscription::query()->firstOrFail();

        // أول شهر بينسجل فوراً: 30 − 17 + 1 = 14 يوم من 30، بتاريخ أول الشهر
        $first = ClientService::query()->where('period', '2026-08')->firstOrFail();
        $this->assertSame('140.00', $first->amount);
        $this->assertSame('2026-08-01', $first->service_date->toDateString());
        $this->assertStringContainsString('(14 يوم من 30)', $first->title);
        $this->assertSame('140.00', $client->fresh()->outstandingAmount($this->ils->id));
        $this->assertSame(['2026-09', '2026-10'], $sub->pendingMonths('2026-10'));

        $this->actingAs($this->user)->post(route('cp.subscriptions.confirm.store'), [
            'month' => '2026-10',
            'items' => [
                $sub->id.'|2026-09' => ['on' => 1, 'amount' => '300.00'],
                $sub->id.'|2026-10' => ['amount' => '300.00'],
            ],
        ])->assertRedirect();
        $this->assertSame('440.00', $client->fresh()->outstandingAmount($this->ils->id));

        // تأكيد نفس الشهر مرتين بيسجّله مرة وحدة
        $this->actingAs($this->user)->post(route('cp.subscriptions.confirm.store'), [
            'items' => [$sub->id.'|2026-09' => ['on' => 1, 'amount' => '300.00']],
        ]);
        $this->assertSame('440.00', $client->fresh()->outstandingAmount($this->ils->id));

        // وقف بنص الشهر: الأيام = يوم الإيقاف
        $this->actingAs($this->user)->post(route('cp.subscriptions.stop', $sub), ['ends_on' => '10/9/2026'])->assertRedirect();
        $this->assertSame('100.00', ClientService::query()->where('period', '2026-09')->value('amount'));
        $this->assertSame([], $sub->fresh()->pendingMonths('2026-10'));

        $this->actingAs($this->user)->delete(route('cp.subscriptions.destroy', $sub))->assertSessionHas('error');
    }

    public function test_partial_month_rules_day_31_and_february(): void
    {
        $client = Client::query()->create(['name' => 'زبون الأيام', 'is_active' => true]);
        $sub = new ClientSubscription(['client_id' => $client->id, 'title' => 'x', 'amount' => 300, 'starts_on' => '2026-12-31']);
        $this->assertSame([1, 30], $sub->daysIn('2026-12')); // يوم 31 بينحسب 30
        $this->assertSame([30, 30], $sub->daysIn('2027-01'));

        $feb = new ClientSubscription(['client_id' => $client->id, 'title' => 'x', 'amount' => 300, 'starts_on' => '2027-02-01', 'ends_on' => '2027-02-28']);
        $this->assertSame([30, 30], $feb->daysIn('2027-02')); // شباط 30
        $this->assertSame('300.00', $feb->suggestedAmount('2027-02'));
    }

    public function test_mid_month_start_is_recorded_now_and_next_month_is_full(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00'));
        $client = Client::query()->create(['name' => 'زبون نص شهر', 'is_active' => true]);
        $type = ServiceType::query()->create(['name' => 'إدارة صفحات', 'billing' => 'monthly', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('cp.subscriptions.store'), [
            'client_id' => $client->id, 'service_type_id' => $type->id, 'amount' => 300, 'starts_on' => '15/10/2026',
        ])->assertRedirect();
        $sub = ClientSubscription::query()->firstOrFail();

        $this->assertSame('160.00', ClientService::query()->where('period', '2026-10')->value('amount')); // 16 يوم من 30
        $this->assertCount(0, \App\Http\Controllers\Cp\ClientSubscriptionController::pending('2026-10'));
        $this->assertNull($sub->accruing());

        $this->travelTo(CarbonImmutable::parse('2026-11-01 08:00'));
        $rows = \App\Http\Controllers\Cp\ClientSubscriptionController::pending('2026-11');
        $this->assertSame(['2026-11'], $rows->pluck('period')->all());
        $this->assertSame('300.00', $rows[0]['amount']);
        $this->actingAs($this->user)->get(route('cp.dashboard'))->assertSee('فواتير الاشتراكات جاهزة');

        $this->actingAs($this->user)->post(route('cp.subscriptions.confirm.store'), [
            'items' => [$sub->id.'|2026-11' => ['on' => 1, 'amount' => '300.00']],
        ])->assertRedirect();
        $this->assertSame('460.00', $client->fresh()->outstandingAmount($this->ils->id));
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

    public function test_service_settings_have_no_billing_and_keep_phases(): void
    {
        // «طريقة الفوترة» انشالت من الإعدادات: الخدمة الجديدة لمرة وحدة، وتصميم وتنفيذ بيضل
        $this->actingAs($this->user)->post(route('cp.settings.services.store'), [
            'name' => 'صيانة', 'has_phases' => 1, 'default_price' => 200, 'is_active' => 0,
        ])->assertRedirect();
        $t = ServiceType::query()->where('name', 'صيانة')->firstOrFail();
        $this->assertFalse($t->isMonthly());
        $this->assertTrue($t->has_phases);
        $this->assertFalse($t->is_active);
        $this->actingAs($this->user)->get(route('cp.settings.index'))->assertOk()->assertDontSee('طريقة الفوترة');
    }
}
