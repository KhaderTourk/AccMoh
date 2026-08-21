<?php

namespace App\Http\Controllers\Cp\Concerns;

use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\ServiceType;

trait LoadsFinanceLookups
{
    protected function financeLookups(): array
    {
        return [
            'currencies' => Currency::query()->active()->get(),
            'paymentMethods' => PaymentMethod::query()->active()->get(),
            'funds' => Fund::query()->orderBy('id')->get(),
            'serviceTypes' => ServiceType::query()->active()->get(),
            'expenseCategories' => ExpenseCategory::query()->active()->get(),
        ];
    }
}
