<?php

namespace App\Http\Controllers\Cp;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\Vendor;
use App\Services\Export\PdfExporter;
use App\Support\Phone;
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
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('job_title', 'like', "%{$term}%")
                    ->orWhere('work_description', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
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
            'charges' => fn ($q) => $q->with(['currency', 'fxCurrency'])
                ->orderBy('charge_date')
                ->orderBy('id'),
            'cashPayments' => fn ($q) => $q->with(['currency', 'fxCurrency', 'paymentMethod', 'fund'])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id'),
        ]);
        $currencies = Currency::query()->active()->get();

        return view('cp.finance.vendors.show', $this->showPayload($vendor, $currencies));
    }

    public function exportPdf(Vendor $vendor, PdfExporter $pdf)
    {
        $this->assertType($vendor);
        $vendor->load([
            'charges' => fn ($q) => $q->with(['currency', 'fxCurrency'])
                ->orderBy('charge_date')
                ->orderBy('id'),
            'cashPayments' => fn ($q) => $q->with(['currency', 'fxCurrency', 'paymentMethod', 'fund'])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id'),
        ]);

        $data = $this->showPayload($vendor, Currency::query()->active()->get());
        $data['exporting'] = true;

        return $pdf->download(
            'cp.finance.vendors.print',
            $data,
            $this->type()->routePrefix().'-'.$vendor->id.'.pdf'
        );
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

        if ($vendor->hasFinancialHistory()) {
            $vendor->update(['is_active' => false]);

            return redirect()->route('cp.'.$type->routePrefix().'.index')
                ->with('success', 'تم الأرشفة لأن هناك سجلات مرتبطة.');
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => Phone::rules(),
            'notes' => ['nullable', 'string'],
        ];
        if ($this->type() === VendorType::Worker) {
            $rules['job_title'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['work_description'] = ['nullable', 'string', 'max:255'];
        }

        return $request->validate($rules, ['phone.regex' => Phone::message()]) + ['is_active' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected function showPayload(Vendor $vendor, $currencies): array
    {
        $type = $this->type();

        return [
            'vendor' => $vendor,
            'currencies' => $currencies,
            'type' => $type,
            'businessFundId' => Fund::business()->id,
            'exportedAt' => now()->format('Y-m-d H:i'),
            'title' => $vendor->name,
            'subtitle' => trim(implode(' · ', array_filter([
                $type->label(),
                $vendor->job_title ?: $vendor->work_description,
                $vendor->phone,
            ]))),
        ];
    }
}
