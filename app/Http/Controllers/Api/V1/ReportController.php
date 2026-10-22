<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Cp\ReportController as CpReportController;
use App\Services\Finance\BalanceService;
use App\Services\Finance\ProfitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ReportController extends CpReportController
{
    public function index(Request $request, BalanceService $balances, ProfitService $profit): JsonResponse
    {
        $payload = $this->payload($request, $balances, $profit, false);

        return response()->json(['data' => Arr::except($payload, [
            'filterQuery', 'exportedAt', 'title', 'forExport',
            // lookup lists are available from GET /lookups
            'clients', 'persons', 'currencies', 'ilsCurrency', 'usdCurrency',
            'paymentMethods', 'funds', 'serviceTypes', 'vendors',
        ])]);
    }
}
