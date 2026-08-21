<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\OfflineSnapshotBuilder;
use Illuminate\Http\JsonResponse;

class BootstrapController extends Controller
{
    public function __invoke(OfflineSnapshotBuilder $snapshot): JsonResponse
    {
        return response()->json($snapshot->build());
    }
}
