<?php

namespace App\Console\Commands;

use App\Models\ClientService;
use App\Models\ClientSubscription;
use App\Models\ServiceType;
use App\Support\Money;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * تسليم 1/11 §2.4: الخدمات الشهرية الموجودة بتصير اشتراكات على زبائنها، و«إدارة الإعلانات الممولة» بتنطفي.
 *
 * - ولا حركة قديمة بتتغيّر ولا رصيد بيتحرك: بس بينضاف سطر اشتراك.
 * - الاشتراك بيبلّش من الشهر اللي بعد آخر شهر مسجّل، عشان ما يتسجّل شهر مرتين.
 * - زبون آخر خدمة شهرية إله أقدم من الشهر الماضي بيطلع بالتقرير كـ«وقف» وما بينعمل إله اشتراك.
 * - خدمة الإعلانات الممولة: is_active = false، واشتراكاتها الشغّالة بتوقف آخر يوم 31/10/2026.
 * - بدون --apply بيطلع تقرير بس.
 */
class ConvertMonthlyServices extends Command
{
    protected $signature = 'accounts:convert-monthly-services
        {--apply : احفظ التغيير (بدونها تقرير بس)}
        {--skip=* : أرقام زبائن ما بدك يتعملهم اشتراك}
        {--today= : تاريخ اليوم للتجربة (YYYY-MM-DD)}';

    protected $description = 'Turn existing monthly services into client subscriptions and switch off the sponsored-ads service, without touching any movement';

    public const ADS_STOP = '2026-10-31';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $skip = array_map('intval', (array) $this->option('skip'));
        $today = CarbonImmutable::parse($this->option('today') ?: 'today');
        $lastMonth = $today->subMonthNoOverflow()->format('Y-m');
        $create = [];
        $stale = [];
        $adsTypes = [];
        $adsSubs = [];

        $types = ServiceType::withoutGlobalScopes()->get();
        foreach ($types as $type) {
            TenantContext::set((int) $type->tenant_id);
            $isAds = str_contains((string) $type->name, 'الإعلانات الممولة') || str_contains((string) $type->name, 'إعلانات ممولة');
            if ($isAds) {
                if ($type->is_active) {
                    $adsTypes[] = $type;
                }
                foreach (ClientSubscription::query()->where('service_type_id', $type->id)
                    ->where('starts_on', '<=', self::ADS_STOP)
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', self::ADS_STOP))->with('client')->get() as $s) {
                    $adsSubs[] = $s;
                }

                continue;
            }
            if ($type->billing !== 'monthly') {
                continue;
            }
            $byClient = ClientService::query()->where('service_type_id', $type->id)->whereNull('subscription_id')
                ->with('client')->orderBy('service_date')->get()->groupBy('client_id');
            foreach ($byClient as $clientId => $rows) {
                $client = $rows->first()->client;
                if (! $client || ClientSubscription::query()->where('client_id', $clientId)->where('service_type_id', $type->id)->exists()) {
                    continue;
                }
                $last = $rows->last();
                $row = [
                    'tenant' => (int) $type->tenant_id,
                    'client' => $client,
                    'type' => $type,
                    'amount' => Money::of($last->amount),
                    'currency_id' => $last->currency_id,
                    'first' => $rows->first()->service_date->format('Y-m'),
                    'last' => $last->service_date->format('Y-m'),
                    'count' => $rows->count(),
                    'starts_on' => $last->service_date->startOfMonth()->addMonthNoOverflow()->toDateString(),
                ];
                if ($row['last'] < $lastMonth || in_array((int) $clientId, $skip, true)) {
                    $stale[] = $row;
                } else {
                    $create[] = $row;
                }
            }
        }
        TenantContext::clear();

        $this->info('اشتراكات جديدة ('.count($create).'):');
        $this->table(['#', 'الزبون', 'الخدمة', 'المبلغ', 'أول شهر مسجّل', 'آخر شهر مسجّل', 'الاشتراك من'], array_map(fn ($r) => [
            $r['client']->id, $r['client']->directoryLabel(), $r['type']->name, $r['amount'], $r['first'], $r['last'], $r['starts_on'],
        ], $create));
        $this->info('ما رح ينعملهم اشتراك (آخر شهر أقدم من '.$lastMonth.' أو مستثنيين) ('.count($stale).'):');
        $this->table(['#', 'الزبون', 'الخدمة', 'آخر شهر مسجّل'], array_map(fn ($r) => [
            $r['client']->id, $r['client']->directoryLabel(), $r['type']->name, $r['last'],
        ], $stale));
        $this->info('خدمة الإعلانات الممولة بتنطفي: '.(count($adsTypes) ? implode('، ', array_map(fn ($t) => $t->name, $adsTypes)) : 'ولا وحدة'));
        $this->info('اشتراكات إعلانات بتوقف آخر يوم '.self::ADS_STOP.' ('.count($adsSubs).'): '
            .implode('، ', array_map(fn ($s) => $s->client?->directoryLabel() ?? '#'.$s->client_id, $adsSubs)));

        if (! $apply) {
            $this->warn('تجربة بس، ما انحفظ إشي. للحفظ: --apply');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($create, $adsTypes, $adsSubs) {
            foreach ($create as $r) {
                TenantContext::set($r['tenant']);
                $sub = ClientSubscription::query()->create([
                    'client_id' => $r['client']->id,
                    'service_type_id' => $r['type']->id,
                    'title' => $r['type']->name,
                    'amount' => $r['amount'],
                    'currency_id' => $r['currency_id'],
                    'starts_on' => $r['starts_on'],
                    'notes' => 'تحويل من الخدمات الشهرية ('.$r['count'].' شهر مسجّل قبل، آخرهم '.$r['last'].')',
                ]);
                $sub->forceFill(['tenant_id' => $r['tenant']])->save();
            }
            foreach ($adsTypes as $t) {
                $t->forceFill(['is_active' => false])->save();
            }
            foreach ($adsSubs as $s) {
                $s->forceFill(['ends_on' => self::ADS_STOP])->save();
            }
        });
        TenantContext::clear();
        $this->info('انحفظ.');

        return self::SUCCESS;
    }
}
