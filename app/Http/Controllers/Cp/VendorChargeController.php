<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Currency;
use App\Models\Vendor;
use App\Models\VendorCharge;
use App\Services\Finance\VendorChargeService;
use Illuminate\Http\Request;

class VendorChargeController extends Controller
{
    use LoadsFinanceLookups;

    public function create(Request $request)
    {
        $vendor = $this->resolveVendor($request->vendor_id);

        return view('cp.finance.vendor-charges.form', [
            'charge' => new VendorCharge([
                'vendor_id' => $vendor->id,
                'charge_date' => now()->toDateString(),
            ]),
            'vendor' => $vendor,
            'vendors' => Vendor::query()->active()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    public function store(Request $request, VendorChargeService $service)
    {
        $data = $this->validated($request);
        $this->resolveVendor($data['vendor_id']);

        try {
            $charge = $service->create($data);
            $charge->load('vendor');
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('cp.'.$charge->vendor->type->routePrefix().'.show', $charge->vendor_id)
            ->with('success', 'تم التسجيل.');
    }

    public function edit(VendorCharge $vendorCharge)
    {
        $vendorCharge->load(['vendor', 'currency', 'fxCurrency']);

        return view('cp.finance.vendor-charges.form', [
            'charge' => $vendorCharge,
            'vendor' => $vendorCharge->vendor,
            'vendors' => Vendor::query()->orderBy('name')->get(),
        ] + $this->financeLookups());
    }

    public function update(Request $request, VendorCharge $vendorCharge, VendorChargeService $service)
    {
        try {
            $service->update($vendorCharge, $this->validated($request, $vendorCharge));
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $vendorCharge->load('vendor');

        return redirect()
            ->route('cp.'.$vendorCharge->vendor->type->routePrefix().'.show', $vendorCharge->vendor_id)
            ->with('success', 'تم التحديث.');
    }

    public function destroy(VendorCharge $vendorCharge, VendorChargeService $service)
    {
        $vendor = $vendorCharge->vendor;

        try {
            $service->delete($vendorCharge);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('cp.'.$vendor->type->routePrefix().'.show', $vendor)
            ->with('success', 'تم الحذف.');
    }

    protected function resolveVendor(mixed $id): Vendor
    {
        abort_unless(filled($id), 404);

        return Vendor::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?VendorCharge $charge = null): array
    {
        $isFx = $request->boolean('requires_fx');
        $ils = Currency::byCode('ILS');
        $usd = Currency::byCode('USD');

        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => [$isFx ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'source_amount' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'exchange_rate' => [$isFx ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'charge_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($charge) {
            $data['vendor_id'] = $charge->vendor_id;
        }

        $data['currency_id'] = $ils->id;
        $data['description'] = null;

        if ($isFx) {
            $data['fx_currency_id'] = $usd->id;
        } else {
            $data['source_amount'] = null;
            $data['exchange_rate'] = null;
            $data['fx_currency_id'] = null;
        }

        return $data;
    }
}
