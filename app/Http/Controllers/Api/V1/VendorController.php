<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VendorType;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\VendorController as CpVendorController;
use App\Models\Currency;
use App\Models\Vendor;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves both /workers and /suppliers; the type comes from the route name (api.workers.* / api.suppliers.*).
 */
class VendorController extends CpVendorController
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        $type = $this->type();
        $currencies = Currency::query()->active()->get();
        $detailColumn = $type === VendorType::Worker ? 'job_title' : 'work_description';
        $columns = [
            'name' => ['column' => 'name'],
            'detail' => ['column' => $detailColumn],
            'phone' => ['column' => 'phone'],
        ];
        foreach ($currencies as $currency) {
            $columns['due-'.$currency->id] = [
                'dir' => 'desc',
                'value' => fn (Vendor $vendor) => (float) $vendor->outstandingAmount($currency->id),
            ];
        }

        $vendors = TableSort::paginate(
            Vendor::query()
                ->active()
                ->ofType($type)
                ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('job_title', 'like', "%{$term}%")
                        ->orWhere('work_description', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                })),
            $columns,
            'name'
        );

        $vendors->getCollection()->transform(fn (Vendor $vendor) => $vendor->toArray() + [
            'outstanding' => $currencies->mapWithKeys(fn ($c) => [$c->code => $vendor->outstandingAmount($c->id)]),
        ]);

        return response()->json($vendors);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->has('is_active')) {
            $request->merge(['is_active' => true]);
        }
        $type = $this->type();
        $vendor = Vendor::query()->create($this->validated($request) + ['type' => $type->value]);

        return $this->created($vendor, 'تم الإضافة.');
    }

    public function show(Vendor $vendor): JsonResponse
    {
        $this->assertType($vendor);
        $vendor->load([
            'charges' => fn ($q) => $q->with(['currency', 'fxCurrency'])->orderBy('charge_date')->orderBy('id'),
            'cashPayments' => fn ($q) => $q->active()->with(['currency', 'fxCurrency', 'paymentMethod', 'fund'])
                ->orderByDesc('occurred_on')->orderByDesc('id'),
            'goodsTakes' => fn ($q) => $q->with(['currency', 'fxCurrency'])->orderByDesc('taken_on')->orderByDesc('id'),
            'balanceMemos' => fn ($q) => $q->with('currency')->orderByDesc('memo_date')->orderByDesc('id'),
        ]);
        $currencies = Currency::query()->active()->get();
        $payload = $this->showPayload($vendor, $currencies);

        return $this->ok([
            'vendor' => $payload['vendor'],
            'type' => $payload['type'],
            'business_fund_id' => $payload['businessFundId'],
            'totals' => $currencies->map(fn ($c) => [
                'currency' => $c->code,
                'billed' => $vendor->billedAmount($c->id),
                'paid' => $vendor->paidAmount($c->id),
                'goods_taken' => $vendor->goodsTakenAmount($c->id),
                'outstanding' => $vendor->outstandingAmount($c->id),
            ])->values(),
        ]);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $this->assertType($vendor);
        $this->keepActiveFlag($request, (bool) $vendor->is_active);
        $vendor->update($this->validated($request));

        return $this->ok($vendor->fresh(), 'تم التحديث.');
    }
}
