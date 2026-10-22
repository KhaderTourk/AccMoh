<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\PersonController as CpPersonController;
use App\Models\Currency;
use App\Models\Person;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonController extends CpPersonController
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        $currencies = Currency::query()->active()->get();

        $members = TableSort::paginate(
            Person::query()
                ->active()
                ->when($request->q, fn ($q, $term) => $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                        ->orWhere('relationship', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%");
                })),
            [
                'name' => ['column' => 'name'],
                'relationship' => ['column' => 'relationship'],
                'phone' => ['column' => 'phone'],
                'net' => [
                    'dir' => 'desc',
                    'value' => function (Person $member) use ($currencies) {
                        $sum = 0.0;
                        foreach ($currencies as $currency) {
                            $sum += (float) $member->netAmount($currency->id);
                        }

                        return $sum;
                    },
                ],
            ],
            'name'
        );

        $members->getCollection()->transform(fn (Person $member) => $member->toArray() + [
            'net' => $currencies->mapWithKeys(fn ($c) => [$c->code => $member->netAmount($c->id)]),
        ]);

        return response()->json($members);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->has('is_active')) {
            $request->merge(['is_active' => true]);
        }
        $member = Person::query()->create($this->validated($request));

        return $this->created($member, 'تم إضافة الشخص.');
    }

    public function show(Person $person): JsonResponse
    {
        $payload = $this->showPayload($person);
        $currencies = $payload['currencies'];

        return $this->ok([
            'member' => $payload['member'],
            'currencies' => $currencies,
            'totals' => $currencies->map(fn ($c) => [
                'currency' => $c->code,
                'incoming' => $person->incomingAmount($c->id),
                'outgoing' => $person->outgoingAmount($c->id),
                'goods_taken' => $person->goodsTakenAmount($c->id),
                'net' => $person->netAmount($c->id),
            ])->values(),
        ]);
    }

    public function update(Request $request, Person $person): JsonResponse
    {
        $this->keepActiveFlag($request, (bool) $person->is_active);
        $person->update($this->validated($request));

        return $this->ok($person->fresh(), 'تم التحديث.');
    }
}
