<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\ServiceTypeController as CpServiceTypeController;
use App\Models\ServiceType;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceTypeController extends CpServiceTypeController
{
    use RespondsWithJson;

    public function index(): JsonResponse
    {
        return response()->json(TableSort::paginate(
            ServiceType::query()->with('defaultCurrency'),
            [
                'name' => ['column' => 'name'],
                'description' => ['column' => 'description'],
                'status' => ['column' => 'is_active', 'dir' => 'desc'],
            ],
            'name',
            30
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $type = ServiceType::query()->create($this->validated($request));

        return $this->created($type, 'تم إضافة الخدمة.');
    }

    public function show(ServiceType $serviceType): JsonResponse
    {
        return $this->ok($serviceType->load('defaultCurrency'));
    }

    public function update(Request $request, ServiceType $serviceType): JsonResponse
    {
        $serviceType->update($this->validated($request));

        return $this->ok($serviceType->fresh(), 'تم التحديث.');
    }
}
