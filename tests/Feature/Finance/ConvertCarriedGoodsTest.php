<?php

namespace Tests\Feature\Finance;

use App\Models\Client;
use App\Models\ClientGoodsTake;
use App\Models\Currency;
use App\Models\PartyBalanceMemo;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertCarriedGoodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_carried_goods_take_becomes_opening_memo_with_same_balance(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        TenantContext::set((int) $user->tenant_id);
        $ils = Currency::query()->where('code', 'ILS')->firstOrFail();
        $client = Client::query()->create(['name' => 'زبون الترحيل', 'is_active' => true]);
        ClientGoodsTake::query()->create([
            'client_id' => $client->id, 'party_type' => 'client', 'party_id' => $client->id,
            'title' => 'رصيد مرحل', 'amount' => '198.20', 'currency_id' => $ils->id, 'taken_on' => '2026-01-05',
        ]);
        ClientGoodsTake::query()->create([
            'client_id' => $client->id, 'party_type' => 'client', 'party_id' => $client->id,
            'title' => 'خصم', 'amount' => '50', 'currency_id' => $ils->id, 'taken_on' => '2026-02-01',
        ]);
        $before = $client->fresh()->outstandingAmount($ils->id);
        TenantContext::clear();

        $this->artisan('accounts:convert-carried-goods')->assertSuccessful();
        $this->assertSame(2, ClientGoodsTake::withoutGlobalScopes()->count());

        $this->artisan('accounts:convert-carried-goods', ['--apply' => true])->assertSuccessful();

        TenantContext::set((int) $user->tenant_id);
        $this->assertSame($before, $client->fresh()->outstandingAmount($ils->id));
        $this->assertSame(['خصم'], ClientGoodsTake::query()->pluck('title')->all());
        $memo = PartyBalanceMemo::query()->firstOrFail();
        $this->assertSame('198.20', (string) $memo->amount);
        $this->assertSame('2026-01-05', $memo->memo_date->toDateString());
        $this->assertSame((int) $user->tenant_id, (int) $memo->tenant_id);
        TenantContext::clear();
    }
}
