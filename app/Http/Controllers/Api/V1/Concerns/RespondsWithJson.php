<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Exceptions\FinanceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithJson
{
    protected function ok(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $message,
            'data' => $data,
        ], fn ($v) => $v !== null), $status);
    }

    protected function created(mixed $data, string $message): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    /**
     * Run a finance operation and turn business-rule failures into 422 responses.
     */
    protected function attempt(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (FinanceException $e) {
            abort(response()->json(['message' => $e->getMessage()], 422));
        }
    }

    /**
     * The CP forms always post the is_active checkbox; API clients may omit it on update.
     */
    protected function keepActiveFlag(Request $request, bool $current): void
    {
        if (! $request->has('is_active')) {
            $request->merge(['is_active' => $current]);
        }
    }
}
