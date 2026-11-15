<?php

namespace Tests\Feature\Finance;

use App\Enums\VendorType;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCharge;
use App\Models\VendorChargeType;
use App\Models\VendorSubscription;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** شاشة الموظفين على قالب الزبائن (15/11/2026): القائمة والكروت و«راتب شهري» ونافذة راتب الشهر. */
class WorkersScreenTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Currency $ils;

    protected VendorChargeType $salary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $this->user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Fund::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
        TenantContext::set((int) $this->user->tenant_id);
        $this->ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        VendorChargeType::seedDefaults();
        $this->salary = VendorChargeType::query()->for(VendorType::Worker)->where('name', 'راتب')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        TenantContext::clear();
        parent::tearDown();
    }

    protected function worker(string $name = 'محمد أبو ندى'): Vendor
    {
        return Vendor::query()->create(['name' => $name, 'type' => VendorType::Worker->value, 'job_title' => 'مصمم جرافيك', 'is_active' => true]);
    }

    public function test_list_shows_the_new_screen_with_salary_and_green_owed_card(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));
        $w = $this->worker();
        VendorCharge::query()->create(['vendor_id' => $w->id, 'title' => 'مكافأة', 'amount' => '3200.00', 'currency_id' => $this->ils->id, 'charge_date' => '2026-11-05']);
        VendorSubscription::query()->create(['vendor_id' => $w->id, 'charge_type_id' => $this->salary->id, 'title' => 'راتب', 'amount' => '1800.00', 'currency_id' => $this->ils->id, 'starts_on' => '2026-11-01']);

        $this->actingAs($this->user)->get(route('cp.workers.index'))->assertOk()
            ->assertSee('cl2-big credit', false)
            ->assertSee('"job":"مصمم جرافيك"', false)
            ->assertSee('"ils":3200', false)
            ->assertSee('"amount":1800', false)
            ->assertSee('"owed":3200', false)
            ->assertDontSee('cp.pin.toggle');
    }

    public function test_salary_sheet_adds_changes_and_stops_without_losing_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));
        $w = $this->worker();
        $put = fn (array $subs, bool $on = true, array $extra = []) => $this->actingAs($this->user)->put(route('cp.workers.update', $w), [
            'name' => $w->name, 'return' => 'list', 'subs_sent' => 1, 'subs_on' => $on ? 1 : 0, 'subs' => $subs,
        ] + $extra);

        $put([['type' => $this->salary->id, 'amount' => 1800]], true, ['stay' => 1, 'panel' => 'subs'])
            ->assertRedirect(route('cp.workers.index', ['flash' => $w->id, 'edit' => $w->id, 'panel' => 'subs']));
        $sub = VendorSubscription::query()->firstOrFail();
        $this->assertSame('2026-11-01', $sub->starts_on->toDateString());

        $put([['type' => $this->salary->id, 'amount' => 1900]]);
        $this->assertSame('1900.00', $sub->fresh()->amount);

        // شهر منضاف: الإطفاء بيوقّفه وبتضل الحركة.
        TenantContext::set((int) $this->user->tenant_id);
        VendorCharge::query()->create(['vendor_id' => $w->id, 'subscription_id' => $sub->id, 'period' => '2026-11', 'title' => 'راتب', 'amount' => '1900.00', 'currency_id' => $this->ils->id, 'charge_date' => '2026-11-30']);
        $this->assertSame(1, $sub->months()->count());
        $put([], false)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-15', $sub->fresh()->ends_on->toDateString());
        $this->assertSame(1, VendorCharge::query()->count());
    }

    public function test_salary_month_window_dates_on_last_day_prorates_first_and_stops(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));
        $w = $this->worker();
        $sub = VendorSubscription::query()->create(['vendor_id' => $w->id, 'charge_type_id' => $this->salary->id, 'title' => 'راتب', 'amount' => '1800.00', 'currency_id' => $this->ils->id, 'starts_on' => '2026-10-01']);
        $add = fn (array $data) => $this->actingAs($this->user)->post(route('cp.vendor-subscriptions.month', $sub), $data + ['currency_id' => $this->ils->id]);

        $add(['period' => '2026-10', 'amount' => 1260, 'start_day' => 10, 'notes' => 'راتب 10/2026 · من 10/10/2026'])
            ->assertRedirect(route('cp.workers.show', $w));
        $oct = VendorCharge::query()->where('period', '2026-10')->firstOrFail();
        $this->assertSame('2026-10-31', $oct->charge_date->toDateString());
        $this->assertSame('راتب', $oct->title);
        $this->assertSame('2026-10-10', $sub->fresh()->starts_on->toDateString());

        // نفس الشهر مرة ثانية بالغلط ممنوع.
        $add(['period' => '2026-10', 'amount' => 1800])->assertSessionHasErrors('period');

        $add(['period' => '2026-11', 'amount' => 900, 'stop' => 1, 'end_day' => 15]);
        $nov = VendorCharge::query()->where('period', '2026-11')->firstOrFail();
        $this->assertSame('2026-11-15', $nov->charge_date->toDateString());
        $this->assertSame('2026-11-15', $sub->fresh()->ends_on->toDateString());
    }

    public function test_follow_up_flags_missing_last_month_salary_and_head_counts_this_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-10'));
        $w = $this->worker();
        VendorSubscription::query()->create(['vendor_id' => $w->id, 'charge_type_id' => $this->salary->id, 'title' => 'راتب', 'amount' => '1500.00', 'currency_id' => $this->ils->id, 'starts_on' => '2026-11-01']);
        VendorCharge::query()->create(['vendor_id' => $w->id, 'title' => 'مكافأة', 'amount' => '500.00', 'currency_id' => $this->ils->id, 'charge_date' => '2026-12-02']);

        $this->actingAs($this->user)->get(route('cp.workers.index'))->assertOk()
            ->assertSee('"missLast":true', false)
            ->assertSee('"dues":500', false)
            ->assertSee('"lastLabel":"11/2026"', false);
    }
}
