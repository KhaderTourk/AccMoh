<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\PartyBalanceMemoController as CpPartyBalanceMemoController;
use App\Models\PartyBalanceMemo;
use App\Services\Api\ApiJournal;
use App\Services\Finance\PartyBalanceMemoService;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartyBalanceMemoController extends CpPartyBalanceMemoController
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        return response()->json(
            PartyBalanceMemo::query()
                ->with(['party', 'currency'])
                ->when($request->party_type, fn ($q, $type) => $q->where('party_type', $type))
                ->when($request->party_id, fn ($q, $id) => $q->where('party_id', $id))
                ->when($request->currency_id, fn ($q, $id) => $q->where('currency_id', $id))
                ->when($request->reason, fn ($q, $reason) => $q->where('reason', $reason))
                ->tap(fn ($q) => DateRange::constrain($q, 'memo_date', $from, $to))
                ->orderByDesc('memo_date')
                ->orderByDesc('id')
                ->paginate(min(100, max(1, (int) $request->input('per_page', 20))))
        );
    }

    public function store(Request $request, PartyBalanceMemoService $service): JsonResponse
    {
        $memo = $this->attempt(fn () => $service->create($this->validated($request)));

        return $this->created($memo->load(['party', 'currency']), 'تم تسجيل الرصيد المرحّل دون تحريك الصندوق.');
    }

    public function transferStore(Request $request, PartyBalanceMemoService $service): JsonResponse
    {
        $memo = $this->attempt(fn () => $service->transfer($this->validatedTransfer($request)));

        return $this->created($memo->load(['party', 'currency']), 'تم ترحيل الرصيد بين الحسابين دون تحريك الصندوق.');
    }

    public function show(PartyBalanceMemo $balance_memo): JsonResponse
    {
        return $this->ok($balance_memo->load(['party', 'currency']));
    }

    public function update(Request $request, PartyBalanceMemo $balance_memo, PartyBalanceMemoService $service): JsonResponse
    {
        if (! $balance_memo->isTransfer() && ! $request->filled('party_key') && ! $request->filled('party_type')) {
            $request->merge(['party_type' => $balance_memo->party_type, 'party_id' => $balance_memo->party_id]);
        }
        $data = $balance_memo->isTransfer()
            ? $this->validatedShared($request)
            : $this->validated($request, $balance_memo);

        if ($balance_memo->group_id) {
            // Both legs are changed with one query update, which fires no model events.
            app(ApiJournal::class)->watch(...PartyBalanceMemo::query()->where('group_id', $balance_memo->group_id)->get()->all());
        }
        $this->attempt(fn () => $service->update($balance_memo, $data));

        return $this->ok($balance_memo->fresh(['party', 'currency']), 'تم تحديث الرصيد المرحّل.');
    }

    protected function mergeParty(Request $request, string $key, string $typeField, string $idField): void
    {
        parent::mergeParty($request, $key, $typeField, $idField);

        // API clients send *_type + *_id directly instead of the CP's *_party_key.
        if (! $request->filled($key) && $request->filled($typeField) && $request->filled($idField)) {
            $request->merge([$key => $request->input($typeField).':'.$request->input($idField)]);
        }
    }
}
