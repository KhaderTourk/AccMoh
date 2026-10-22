<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Cp\DashboardController as CpDashboardController;
use App\Services\Finance\BalanceService;
use Illuminate\Http\JsonResponse;

class DashboardController extends CpDashboardController
{
    public function index(BalanceService $balances): JsonResponse
    {
        // Same numbers the CP dashboard renders, returned as JSON.
        return response()->json(['data' => parent::index($balances)->getData()]);
    }
}
