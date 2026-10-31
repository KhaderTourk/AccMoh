<?php

namespace App\Http\Controllers\Cp;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\Vendor;
use App\Services\Export\PdfExporter;
use App\Services\Finance\PartyPageService;
use App\Support\DateRange;
use App\Support\PartyStatement;
use App\Support\Phone;
use App\Support\TableSort;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $type = $this->type();
        $currencies = Currency::query()->active()->get();
        $prefix = $type->routePrefix();

        $rows = Vendor::query()->ofType($type)->orderBy('name')->get()
            ->map(fn (Vendor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sub' => (string) ($type === VendorType::Worker ? $v->job_title : $v->work_description),
                'phone' => (string) $v->phone,
                'notes' => (string) $v->notes,
                'archived' => ! $v->is_active,
                'balances' => $this->balances($currencies, fn ($id) => $v->outstandingAmount($id)),
                'fields' => [
                    'detail' => (string) ($type === VendorType::Worker ? $v->job_title : $v->work_description),
                    'salary_amount' => $v->salary_amount ? (float) $v->salary_amount : null,
                    'salary_from' => $v->salary_from?->toDateString(),
                    'salary_to' => $v->salary_to?->toDateString(),
                ],
                'show' => route("cp.{$prefix}.show", $v),
                'pdf' => route("cp.{$prefix}.export-pdf", $v),
                'update' => route("cp.{$prefix}.update", $v),
                'archive' => route("cp.{$prefix}.destroy", $v),
                'restore' => route("cp.{$prefix}.restore", $v),
            ])->values();

        return view('cp.finance.parties.index', [
            'party' => $this->profile(),
            'rows' => $rows,
            'flash' => (int) $request->query('flash'),
            'storeUrl' => route("cp.{$prefix}.store"),
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
        $vendor = Vendor::query()->create($this->validated($request) + ['type' => $type->value, 'is_active' => true]);

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.'.$type->routePrefix().'.index', ['flash' => $vendor->id])->with('success', 'تم الإضافة.');
        }

        return redirect()->route('cp.'.$type->routePrefix().'.show', $vendor)
            ->with('success', 'تم الإضافة.');
    }

    public function show(Vendor $vendor)
    {
        $pages = app(PartyPageService::class);
        $this->assertType($vendor);

        return view('cp.finance.parties.show', $this->pagePayload($vendor, $pages));
    }

    public function exportPdf(Request $request, Vendor $vendor, PdfExporter $pdf)
    {
        $pages = app(PartyPageService::class);
        $this->assertType($vendor);

        return $pdf->download(
            'cp.finance.parties.print',
            PartyStatement::data($request, $this->profile(), $vendor->name, $this->subtitle($vendor), $pages->movements($vendor)),
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

        if ($request->input('return') === 'list') {
            return redirect()->route('cp.'.$this->type()->routePrefix().'.index', ['flash' => $vendor->id])->with('success', 'انحفظ التعديل.');
        }

        return redirect()->route('cp.'.$this->type()->routePrefix().'.show', $vendor)
            ->with('success', 'تم التحديث.');
    }

    public function destroy(Vendor $vendor)
    {
        $this->assertType($vendor);
        $type = $this->type();

        $vendor->update(['is_active' => false]);

        return redirect()->route('cp.'.$type->routePrefix().'.index')
            ->with('success', 'انأرشف، وبتلاقيه مع كل حركاته بتاب «مؤرشف».');
    }

    public function restore(Vendor $vendor)
    {
        $this->assertType($vendor);
        $vendor->update(['is_active' => true]);

        return redirect()->route('cp.'.$this->type()->routePrefix().'.index', ['flash' => $vendor->id])
            ->with('success', 'رجع للقائمة.');
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
            $rules['salary_amount'] = ['nullable', 'numeric', 'gte:0'];
            $rules['salary_from'] = DateRange::inputRules(false);
            $rules['salary_to'] = DateRange::inputRules(false);
        } else {
            $rules['work_description'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules, ['phone.regex' => Phone::message()]);
        if ($request->has('detail')) {
            $data[$this->type() === VendorType::Worker ? 'job_title' : 'work_description'] = $request->input('detail');
        }
        foreach (['salary_from', 'salary_to'] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = DateRange::normalize($data[$f]);
            }
        }
        if (array_key_exists('salary_amount', $data) && ! filled($data['salary_amount'])) {
            $data['salary_amount'] = null;
        }
        // The list window has no archive switch; only the old form sends it.
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }

    /**
     * Payload the API's vendor endpoint still reads.
     *
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
            'exportedAt' => format_date(now(), true),
            'title' => $vendor->name,
            'subtitle' => trim(implode(' · ', array_filter([$type->label(), $vendor->job_title ?: $vendor->work_description, $vendor->phone]))),
        ];
    }

    /** @return array<string, mixed> */
    protected function profile(): array
    {
        $worker = $this->type() === VendorType::Worker;

        return [
            'kind' => $worker ? 'worker' : 'supplier',
            'noun' => $worker ? 'موظف' : 'مورد',
            'plural' => $worker ? 'الموظفين' : 'الموردين',
            'pos' => 'إله', 'neg' => 'مقدّماً',
            'posTab' => 'إلهم', 'negTab' => 'مقدّماً',
            'g1' => $worker ? 'مستحقات' : 'خدمات', 'g2' => 'دفعات',
            'detail' => $worker ? 'المسمّى الوظيفي' : 'شو بيورّد',
            'legal' => true,
        ];
    }

    protected function subtitle(Vendor $vendor): string
    {
        return trim(implode(' · ', array_filter([$vendor->job_title ?: $vendor->work_description, $vendor->phone])));
    }

    /** @return array<string, mixed> */
    protected function pagePayload(Vendor $vendor, PartyPageService $pages): array
    {
        $prefix = $this->type()->routePrefix();
        $methods = \App\Models\PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);

        return [
            'party' => $this->profile(),
            'model' => $vendor,
            'title' => $vendor->name,
            'subtitle' => $this->subtitle($vendor),
            'movements' => $pages->movements($vendor),
            'lookups' => [
                'currencies' => Currency::query()->active()->get(['id', 'code', 'symbol', 'name']),
                'methods' => $methods,
                'fundId' => Fund::business()->id,
                'salary' => $this->type() === VendorType::Worker && $vendor->activeSalary() ? (float) $vendor->activeSalary() : null,
                'partyKey' => 'vendor:'.$vendor->id,
                'targets' => [],
            ],
            'urls' => [
                'charge' => route('cp.vendor-charges.store'),
                'payment' => route('cp.payments.store', ['outgoing']),
                'goods' => route('cp.client-goods-takes.store'),
                'memo' => route('cp.balance-memos.store'),
                'pdf' => route("cp.{$prefix}.export-pdf", $vendor),
                'index' => route("cp.{$prefix}.index"),
                'edit' => route("cp.{$prefix}.edit", $vendor),
                'archive' => $vendor->is_active ? route("cp.{$prefix}.destroy", $vendor) : route("cp.{$prefix}.restore", $vendor),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function balances($currencies, callable $amount): array
    {
        $out = [];
        foreach ($currencies as $currency) {
            $v = $amount($currency->id);
            if (! \App\Support\Money::isZero($v)) {
                $out[] = ['code' => $currency->code, 'amount' => (float) $v, 'label' => $currency->format(\App\Support\Money::abs($v))];
            }
        }

        return $out;
    }
}
