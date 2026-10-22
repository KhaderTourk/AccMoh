<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Cp\LedgerController as CpLedgerController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends CpLedgerController
{
    public function index(Request $request): JsonResponse
    {
        $data = parent::index($request)->getData();

        return response()->json([
            'entries' => $data['entries'],
            'totals' => $data['totals'],
            'types' => $data['types'],
        ]);
    }
}
