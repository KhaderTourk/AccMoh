<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    use LoadsFinanceLookups;

    public function index()
    {
        $types = ServiceType::query()->with('defaultCurrency')->orderBy('name')->paginate(30);

        return view('cp.finance.service-types.index', compact('types'));
    }

    public function create()
    {
        return view('cp.finance.service-types.form', [
            'type' => new ServiceType(['is_active' => true]),
        ] + $this->financeLookups());
    }

    public function store(Request $request)
    {
        ServiceType::query()->create($this->validated($request));

        return redirect()->route('cp.service-types.index')->with('success', 'تم إضافة نوع الخدمة.');
    }

    public function edit(ServiceType $serviceType)
    {
        return view('cp.finance.service-types.form', [
            'type' => $serviceType,
        ] + $this->financeLookups());
    }

    public function update(Request $request, ServiceType $serviceType)
    {
        $serviceType->update($this->validated($request));

        return redirect()->route('cp.service-types.index')->with('success', 'تم التحديث.');
    }

    public function destroy(ServiceType $serviceType)
    {
        if ($serviceType->clientServices()->exists()) {
            $serviceType->update(['is_active' => false]);

            return redirect()->route('cp.service-types.index')
                ->with('success', 'تم تعطيل نوع الخدمة لأنه مستخدم في سجلات.');
        }

        $serviceType->forceDelete();

        return redirect()->route('cp.service-types.index')->with('success', 'تم الحذف.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_price' => ['nullable', 'numeric', 'gte:0'],
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
