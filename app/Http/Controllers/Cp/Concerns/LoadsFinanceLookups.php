<?php

namespace App\Http\Controllers\Cp\Concerns;

use App\Enums\FundSlug;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\Vendor;
use App\Models\ServiceType;
use App\Support\DateRange;
use Illuminate\Http\Request;

trait LoadsFinanceLookups
{
    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function dateRange(Request $request): array
    {
        [$from, $to] = DateRange::fromRequest($request);
        $request->merge([
            'from' => $from,
            'to' => $to,
        ]);

        return [$from, $to];
    }

    protected function financeLookups(): array
    {
        $funds = Fund::query()->orderBy('id')->get();
        $serviceTypes = ServiceType::query()->active()->get();

        if (! tenantBusinessEnabled()) {
            $funds = $funds->where('slug', '!=', FundSlug::Business->value)->values();
            $serviceTypes = collect();
        }

        $currencies = Currency::query()->active()->get();

        return [
            'currencies' => $currencies,
            'ilsCurrency' => $currencies->firstWhere('code', 'ILS'),
            'usdCurrency' => $currencies->firstWhere('code', 'USD'),
            'paymentMethods' => PaymentMethod::query()->active()->get(),
            'funds' => $funds,
            'serviceTypes' => $serviceTypes,
            'vendors' => tenantBusinessEnabled()
                ? Vendor::query()->active()->orderBy('name')->get()
                : collect(),
        ];
    }
}
