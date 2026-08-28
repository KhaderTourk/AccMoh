<?php

namespace App\Http\Controllers\Cp;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $type = $this->type();
        $vendors = Vendor::query()
            ->ofType($type)
            ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $currencies = Currency::query()->active()->get();

        return view('cp.finance.vendors.index', [
            'vendors' => $vendors,
            'type' => $type,
            'currencies' => $currencies,
        ]);
    }

    public function create()
    {
        $type = $this->type();

        return view('cp.finance.vendors.form', [
            'vendor' => new Vendor(['is_active' => true, 'type' => $type]),
            'type' => $type,
        ]);
    }

    public function store(Request $request)
    {
        $type = $this->type();
        $vendor = Vendor::query()->create($this->validated($request) + ['type' => $type->value]);

        return redirect()->route('cp.'.$type->routePrefix().'.show', $vendor)
            ->with('success', 'تم الإضافة.');
    }

    public function show(Vendor $vendor)
    {
        $this->assertType($vendor);
        $vendor->load([
            'expenses' => fn ($q) => $q->with(['currency', 'paymentMethod', 'category', 'fund'])
                ->latest('expense_date'),
        ]);
        $currencies = Currency::query()->active()->get();

        return view('cp.finance.vendors.show', [
            'vendor' => $vendor,
            'currencies' => $currencies,
            'type' => $this->type(),
            'businessFundId' => Fund::business()->id,
        ]);
    }

    public function edit(Vendor $vendor)
    {
        $this->assertType($vendor);

        return view('cp.finance.vendors.form', [
            'vendor' => $vendor,
            'type' => $this->type(),
        ]);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $this->assertType($vendor);
        $vendor->update($this->validated($request));

        return redirect()->route('cp.'.$this->type()->routePrefix().'.show', $vendor)
            ->with('success', 'تم التحديث.');
    }

    public function destroy(Vendor $vendor)
    {
        $this->assertType($vendor);
        $type = $this->type();

        if ($vendor->expenses()->exists()) {
            $vendor->update(['is_active' => false]);

            return redirect()->route('cp.'.$type->routePrefix().'.index')
                ->with('success', 'تم الأرشفة لأن هناك مصروفات مرتبطة.');
        }

        $vendor->forceDelete();

        return redirect()->route('cp.'.$type->routePrefix().'.index')->with('success', 'تم الحذف.');
    }

    protected function type(): VendorType
    {
        $name = (string) request()->route()?->getName();

        return str_contains($name, 'workers') ? VendorType::Worker : VendorType::Supplier;
    }

    protected function assertType(Vendor $vendor): void
    {
        abort_unless($vendor->type === $this->type(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
