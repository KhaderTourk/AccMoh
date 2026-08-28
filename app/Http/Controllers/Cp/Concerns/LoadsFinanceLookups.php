<?php

namespace App\Http\Controllers\Cp\Concerns;

use App\Enums\FundSlug;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\ServiceType;

trait LoadsFinanceLookups
{
    protected function financeLookups(): array
    {
        $funds = Fund::query()->orderBy('id')->get();
        $categories = ExpenseCategory::query()->active()->get();
        $serviceTypes = ServiceType::query()->active()->get();

        if (! tenantBusinessEnabled()) {
            $funds = $funds->where('slug', '!=', FundSlug::Business->value)->values();
            $categories = $categories->filter(fn ($c) => $c->fund_slug !== FundSlug::Business->value)->values();
            $serviceTypes = collect();
        }

        return [
            'currencies' => Currency::query()->active()->get(),
            'paymentMethods' => PaymentMethod::query()->active()->get(),
            'funds' => $funds,
            'serviceTypes' => $serviceTypes,
            'expenseCategories' => $categories,
        ];
    }
}
