<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BalanceMemoDirection;
use App\Enums\BalanceMemoReason;
use App\Enums\GoodsTakeCategory;
use App\Enums\TransactionType;
use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\Client;
use App\Models\Person;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the CP forms load into their dropdowns, in one call.
 */
class LookupController extends Controller
{
    use LoadsFinanceLookups;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $business = tenantBusinessEnabled();
        $enum = fn (array $cases) => collect($cases)->map(fn ($case) => [
            'value' => $case->value,
            'label' => method_exists($case, 'label') ? $case->label() : $case->name,
        ])->values();

        return response()->json(['data' => $this->financeLookups() + [
            'parties' => [
                'clients' => $business
                    ? Client::query()->active()->orderBy('name')->get(['id', 'name', 'contact_name', 'company_name'])
                    : collect(),
                'persons' => Person::query()->active()->orderBy('name')->get(['id', 'name']),
                'vendors' => $business
                    ? Vendor::query()->active()->orderBy('name')->get(['id', 'name', 'type'])
                    : collect(),
            ],
            'enums' => [
                'party_types' => ['client', 'person', 'vendor'],
                'payment_directions' => ['incoming', 'outgoing'],
                'vendor_types' => $enum(VendorType::cases()),
                'goods_take_categories' => $enum(GoodsTakeCategory::cases()),
                'balance_memo_directions' => $enum(BalanceMemoDirection::cases()),
                'balance_memo_reasons' => $enum([BalanceMemoReason::Opening, BalanceMemoReason::YearRollover]),
                'transaction_types' => $enum(TransactionType::cases()),
            ],
            'features' => [
                'business_enabled' => $business,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'can_finance' => $user->canAccess('finance'),
                'can_manage_users' => $user->canAccess('users'),
            ],
        ]]);
    }
}
