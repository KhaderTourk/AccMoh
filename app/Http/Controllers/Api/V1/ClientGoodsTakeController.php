<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\ClientGoodsTakeController as CpClientGoodsTakeController;
use App\Models\ClientGoodsTake;
use App\Services\Finance\ClientGoodsTakeService;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientGoodsTakeController extends CpClientGoodsTakeController
{
    use RespondsWithJson;

    protected const RELATIONS = ['party', 'client', 'currency', 'fxCurrency'];

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        return response()->json(
            ClientGoodsTake::query()
                ->with(self::RELATIONS)
                ->when($request->party_type, fn ($q, $type) => $q->where('party_type', $type))
                ->when($request->party_id, fn ($q, $id) => $q->where('party_id', $id))
                ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
                ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
                ->tap(fn ($q) => DateRange::constrain($q, 'taken_on', $from, $to))
                ->orderByDesc('taken_on')
                ->orderByDesc('id')
                ->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function store(Request $request, ClientGoodsTakeService $service): JsonResponse
    {
        $data = $this->validated($request);
        $row = $this->attempt(fn () => $service->create($data));

        return $this->created($row->load(self::RELATIONS), 'تم تسجيل المنتج المأخوذ وخصمه من الحساب.');
    }

    public function show(ClientGoodsTake $clientGoodsTake): JsonResponse
    {
        return $this->ok($clientGoodsTake->load(self::RELATIONS));
    }

    public function update(Request $request, ClientGoodsTake $clientGoodsTake, ClientGoodsTakeService $service): JsonResponse
    {
        // The party of an existing row is fixed; the CP form still submits it.
        if (! $request->filled('party_key') && ! $request->filled('party_type')) {
            $request->merge([
                'party_type' => $clientGoodsTake->party_type ?: 'client',
                'party_id' => $clientGoodsTake->party_id ?: $clientGoodsTake->client_id,
            ]);
        }
        $data = $this->validated($request, $clientGoodsTake);
        $updated = $this->attempt(fn () => $service->update($clientGoodsTake, $data));

        return $this->ok($updated->load(self::RELATIONS), 'تم تحديث المنتج المأخوذ.');
    }

    protected function mergeParty(Request $request): void
    {
        parent::mergeParty($request);

        // API clients send party_type + party_id directly instead of the CP's party_key.
        if (! $request->filled('party_key') && $request->filled('party_type') && $request->filled('party_id')) {
            $request->merge(['party_key' => $request->party_type.':'.$request->party_id]);
        }
    }
}
