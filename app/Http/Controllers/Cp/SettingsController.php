<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\ClientService;
use App\Models\ClientSubscription;
use App\Models\PaymentMethod;
use App\Models\ServiceGroup;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Settings page (#7 §6): service groups and services, payment methods, import.
 * Used items can only be switched off; only unused ones can be deleted.
 */
class SettingsController extends Controller
{
    /** Tables that point at a payment method. */
    protected const METHOD_COLUMNS = [
        ['cash_payments', 'payment_method_id'],
        ['client_payments', 'payment_method_id'],
        ['expenses', 'payment_method_id'],
        ['family_loans', 'payment_method_id'],
        ['family_loan_repayments', 'payment_method_id'],
        ['fund_transfers', 'from_payment_method_id'],
        ['fund_transfers', 'to_payment_method_id'],
        ['ledger_entries', 'payment_method_id'],
    ];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['services', 'methods', 'import'], true) ? $request->query('tab') : 'services';

        $serviceUses = ClientService::query()->whereNotNull('service_type_id')
            ->selectRaw('service_type_id, COUNT(*) as n')->groupBy('service_type_id')->pluck('n', 'service_type_id');
        $subUses = ClientSubscription::query()->whereNotNull('service_type_id')
            ->selectRaw('service_type_id, COUNT(*) as n')->groupBy('service_type_id')->pluck('n', 'service_type_id');

        // عدد الحركات اللي بتحمل اسم الخدمة، عشان تنبيه «الاسم الجديد بيطلع على N حركة».
        $titled = ClientService::query()->join('service_types', 'service_types.id', '=', 'client_services.service_type_id')
            ->whereColumn('client_services.title', 'service_types.name')
            ->selectRaw('client_services.service_type_id, COUNT(*) as n')->groupBy('client_services.service_type_id')
            ->pluck('n', 'client_services.service_type_id');

        $groups = ServiceGroup::query()->orderBy('sort_order')->orderBy('name')->get();
        $types = ServiceType::query()->orderBy('name')->get();
        $toRow = fn (ServiceType $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'group' => $t->service_group_id,
            'billing' => $t->billing ?: 'once',
            'phases' => (bool) $t->has_phases,
            'price' => $t->default_price !== null ? rtrim(rtrim((string) $t->default_price, '0'), '.') : '',
            'active' => (bool) $t->is_active,
            'titled' => (int) ($titled[$t->id] ?? 0),
            'uses' => (int) ($serviceUses[$t->id] ?? 0) + (int) ($subUses[$t->id] ?? 0),
            'update' => route('cp.settings.services.update', $t),
            'toggle' => route('cp.settings.services.toggle', $t),
            'delete' => route('cp.settings.services.destroy', $t),
        ];

        $groupRows = $groups->map(fn (ServiceGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'update' => route('cp.settings.groups.update', $g),
            'delete' => route('cp.settings.groups.destroy', $g),
            'services' => $types->where('service_group_id', $g->id)->where('is_active', true)->map($toRow)->values(),
            'total' => $types->where('service_group_id', $g->id)->count(),
        ])->values();
        $ungrouped = $types->whereNull('service_group_id')->where('is_active', true)->map($toRow)->values();
        $stopped = $types->where('is_active', false)->map($toRow)->values();
        $serviceNames = $types->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values();

        $methodUses = $this->methodUses();
        $methods = PaymentMethod::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (PaymentMethod $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'active' => (bool) $m->is_active,
            'uses' => (int) ($methodUses[$m->id] ?? 0),
            'update' => route('cp.settings.methods.update', $m),
            'toggle' => route('cp.settings.methods.toggle', $m),
            'delete' => route('cp.settings.methods.destroy', $m),
        ])->values();

        return view('cp.settings.index', compact('tab', 'groupRows', 'ungrouped', 'stopped', 'serviceNames', 'methods'));
    }

    /* ——— المجموعات ——— */

    public function storeGroup(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $this->assertUniqueGroup($data['name']);
        ServiceGroup::query()->create(['name' => trim($data['name']), 'sort_order' => (int) ServiceGroup::query()->max('sort_order') + 1]);

        return $this->back('services', 'انضافت المجموعة.');
    }

    public function updateGroup(Request $request, ServiceGroup $group)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $this->assertUniqueGroup($data['name'], $group->id);
        $group->update(['name' => trim($data['name'])]);

        return $this->back('services', 'انحفظ اسم المجموعة.');
    }

    public function destroyGroup(ServiceGroup $group)
    {
        if ($group->serviceTypes()->exists()) {
            return $this->back('services', 'المجموعة فيها خدمات، فما بتنحذف. انقل خدماتها أول.', 'error');
        }
        $group->delete();

        return $this->back('services', 'انحذفت المجموعة.');
    }

    /* ——— الخدمات ——— */

    public function storeService(Request $request)
    {
        $data = $this->serviceData($request);
        ServiceType::query()->create($data);

        return $this->back('services', 'انضافت الخدمة.');
    }

    public function updateService(Request $request, ServiceType $serviceType)
    {
        $data = $this->serviceData($request, $serviceType);
        $oldName = $serviceType->name;

        DB::transaction(function () use ($serviceType, $data, $oldName) {
            $serviceType->update($data);
            // Renaming updates the old movements and subscriptions that carry the name.
            if ($oldName !== $data['name']) {
                ClientService::query()->where('service_type_id', $serviceType->id)->where('title', $oldName)
                    ->update(['title' => $data['name']]);
                ClientSubscription::query()->where('service_type_id', $serviceType->id)->where('title', $oldName)
                    ->update(['title' => $data['name']]);
            }
        });

        return $this->back('services', 'انحفظت الخدمة.');
    }

    public function toggleService(ServiceType $serviceType)
    {
        $serviceType->update(['is_active' => ! $serviceType->is_active]);

        return $this->back('services', $serviceType->is_active ? 'الخدمة صارت شغّالة.' : 'انطفت الخدمة: ما بتطلع بأزرار الإضافة، وبتضل بالكشوفات.');
    }

    public function destroyService(ServiceType $serviceType)
    {
        if ($serviceType->clientServices()->exists() || ClientSubscription::query()->where('service_type_id', $serviceType->id)->exists()) {
            return $this->back('services', 'الخدمة مستعملة، فما بتنحذف. بتقدر تطفيها بس.', 'error');
        }
        $serviceType->forceDelete();

        return $this->back('services', 'انحذفت الخدمة.');
    }

    /* ——— طرق الدفع ——— */

    public function storeMethod(Request $request)
    {
        $name = $this->methodName($request);
        PaymentMethod::query()->create([
            'name' => $name,
            'slug' => 'm-'.Str::lower(Str::random(10)),
            'is_active' => true,
            'sort_order' => (int) PaymentMethod::query()->max('sort_order') + 1,
        ]);

        return $this->back('methods', 'انضافت طريقة الدفع.');
    }

    public function updateMethod(Request $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['name' => $this->methodName($request, $paymentMethod->id)]);

        return $this->back('methods', 'انحفظت طريقة الدفع.');
    }

    public function toggleMethod(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        return $this->back('methods', $paymentMethod->is_active ? 'طريقة الدفع صارت شغّالة.' : 'انطفت طريقة الدفع: ما بتطلع بأزرار الإضافة، وبتضل بالكشوفات.');
    }

    public function destroyMethod(PaymentMethod $paymentMethod)
    {
        if (($this->methodUses()[$paymentMethod->id] ?? 0) > 0) {
            return $this->back('methods', 'طريقة الدفع مستعملة، فما بتنحذف. بتقدر تطفيها بس.', 'error');
        }
        $paymentMethod->delete();

        return $this->back('methods', 'انحذفت طريقة الدفع.');
    }

    /* ——— مساعدات ——— */

    /** @return array<int, int> */
    protected function methodUses(): array
    {
        $uses = [];
        foreach (self::METHOD_COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            foreach (DB::table($table)->whereNotNull($column)->selectRaw("{$column} as id, COUNT(*) as n")->groupBy($column)->get() as $row) {
                $uses[(int) $row->id] = ($uses[(int) $row->id] ?? 0) + (int) $row->n;
            }
        }

        return $uses;
    }

    protected function serviceData(Request $request, ?ServiceType $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_group_id' => ['nullable', Rule::exists('service_groups', 'id')],
            'new_group_name' => ['nullable', 'string', 'max:100'],
            'billing' => ['required', Rule::in(['once', 'monthly'])],
            'has_phases' => ['sometimes', 'boolean'],
            'default_price' => ['nullable', 'numeric', 'gte:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        // مجموعة جديدة من آخر قائمة المجموعات جوّا نافذة الخدمة.
        $newGroup = trim((string) ($data['new_group_name'] ?? ''));
        unset($data['new_group_name']);
        if ($newGroup !== '') {
            $data['service_group_id'] = ServiceGroup::query()->where('name', $newGroup)->value('id')
                ?? ServiceGroup::query()->create(['name' => $newGroup, 'sort_order' => (int) ServiceGroup::query()->max('sort_order') + 1])->id;
        }
        $data['has_phases'] = $request->boolean('has_phases');
        $data['default_price'] = filled($data['default_price'] ?? null) ? $data['default_price'] : null;
        $data['name'] = trim($data['name']);
        $dup = ServiceType::query()->where('name', $data['name'])
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))->exists();
        if ($dup) {
            throw ValidationException::withMessages(['name' => 'في خدمة بنفس الاسم.']);
        }
        $data['is_active'] = $request->boolean('is_active', $current?->is_active ?? true);

        return $data;
    }

    protected function methodName(Request $request, ?int $ignoreId = null): string
    {
        $name = trim((string) $request->validate(['name' => ['required', 'string', 'max:255']])['name']);
        if (PaymentMethod::query()->where('name', $name)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            throw ValidationException::withMessages(['name' => 'في طريقة دفع بنفس الاسم.']);
        }

        return $name;
    }

    protected function assertUniqueGroup(string $name, ?int $ignoreId = null): void
    {
        if (ServiceGroup::query()->where('name', trim($name))->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            throw ValidationException::withMessages(['name' => 'في مجموعة بنفس الاسم.']);
        }
    }

    protected function back(string $tab, string $message, string $type = 'success')
    {
        return redirect()->route('cp.settings.index', ['tab' => $tab])->with($type, $message);
    }
}
