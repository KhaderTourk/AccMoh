<?php

namespace App\Http\Controllers\Cp;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Vendor;
use App\Services\Finance\VendorChargeService;
use App\Support\Money;
use App\Support\MonthlyPlan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «أضف هالشهر» للراتب الشهري (الموظفين) والاشتراك الشهري (الموردين). كل ضغطة بتسجّل
 * مستحق واحد للشهر المختار، وما بينضاف نفس الشهر مرتين (قرار 10/11/2026).
 */
class VendorMonthlyController extends Controller
{
    public function store(Request $request, Vendor $vendor, VendorChargeService $charges)
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);
        $period = $data['period'];
        $prefix = $vendor->type->routePrefix();
        $back = redirect()->route("cp.{$prefix}.index");

        $added = DB::transaction(function () use ($vendor, $period, $data, $charges) {
            Vendor::query()->whereKey($vendor->id)->lockForUpdate()->first();
            if (! in_array($period, $vendor->pendingMonths(), true)) {
                return false;
            }
            $charges->create([
                'vendor_id' => $vendor->id,
                'title' => $vendor->monthlyTitle($period),
                'amount' => Money::of($data['amount']),
                'currency_id' => Currency::byCode('ILS')->id,
                'charge_date' => $period.'-01',
            ])->forceFill(['period' => $period])->save();

            return true;
        });

        return $added
            ? $back->with('success', 'انضاف '.$vendor->monthlyNoun().' شهر '.MonthlyPlan::label($period).' لـ'.$vendor->name.'.')
            : $back->with('error', 'الشهر '.MonthlyPlan::label($period).' انضاف قبل، أو مش ضمن '.$vendor->monthlyNoun().' '.$vendor->name.'.');
    }

    /**
     * For the list filter: the vendor's monthly plan when it runs this month or has a month not added yet.
     *
     * @return list<array<string, mixed>>
     */
    public static function plans(Vendor $vendor): array
    {
        if (! $vendor->hasMonthly()) {
            return [];
        }
        $today = CarbonImmutable::today();
        $current = $today->format('Y-m');
        $pending = collect($vendor->pendingMonths($current))
            ->filter(fn ($p) => MonthlyPlan::covers($vendor->salary_from, $vendor->salary_to, $p));
        if ($pending->isEmpty() && ! MonthlyPlan::covers($vendor->salary_from, $vendor->salary_to, $current)) {
            return [];
        }

        return [[
            'title' => $vendor->type === VendorType::Worker ? 'الراتب الشهري' : ($vendor->work_description ?: 'الاشتراك الشهري'),
            'amount' => (float) $vendor->salary_amount,
            'from' => $vendor->salary_from->toDateString(),
            'to' => $vendor->salary_to?->toDateString(),
            'action' => route('cp.vendor-monthly.store', $vendor),
            'pending' => $pending->map(function ($p) use ($vendor) {
                [$days, $inMonth] = MonthlyPlan::daysIn($vendor->salary_from, $vendor->salary_to, $p);

                return [
                    'period' => $p,
                    'label' => MonthlyPlan::label($p),
                    'amount' => (float) $vendor->monthlySuggested($p),
                    'days' => $days,
                    'inMonth' => $inMonth,
                    'amountField' => 'amount',
                    'fields' => ['period' => $p],
                ];
            })->values()->all(),
        ]];
    }
}
