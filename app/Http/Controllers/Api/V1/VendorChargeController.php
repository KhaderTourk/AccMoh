<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\VendorChargeController as CpVendorChargeController;
use App\Models\VendorCharge;
use App\Services\Finance\VendorChargeService;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorChargeController extends CpVendorChargeController
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        return response()->json(
            VendorCharge::query()
                ->with(['vendor', 'currency', 'fxCurrency'])
                ->when($request->vendor_id, fn ($q, $id) => $q->where('vendor_id', $id))
                ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
                ->tap(fn ($q) => DateRange::constrain($q, 'charge_date', $from, $to))
                ->orderByDesc('charge_date')
                ->orderByDesc('id')
                ->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function store(Request $request, VendorChargeService $service): JsonResponse
    {
        $data = $this->validated($request);
        $this->resolveVendor($data['vendor_id']);
        $charge = $this->attempt(fn () => $service->create($data));

        return $this->created($charge->load(['vendor', 'currency', 'fxCurrency']), 'تم التسجيل.');
    }

    public function show(VendorCharge $vendorCharge): JsonResponse
    {
        return $this->ok($vendorCharge->load(['vendor', 'currency', 'fxCurrency']));
    }

    public function update(Request $request, VendorCharge $vendorCharge, VendorChargeService $service): JsonResponse
    {
        if (! $request->filled('vendor_id')) {
            $request->merge(['vendor_id' => $vendorCharge->vendor_id]);
        }
        $data = $this->validated($request, $vendorCharge);
        $this->attempt(fn () => $service->update($vendorCharge, $data));

        return $this->ok($vendorCharge->fresh(['vendor', 'currency', 'fxCurrency']), 'تم التحديث.');
    }
}
