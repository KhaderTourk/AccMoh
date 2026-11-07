<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Person;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;

/** ★ تثبيت/إلغاء تثبيت طرف بالقوائم. */
class PinController extends Controller
{
    public function __invoke(string $kind, int $id): JsonResponse
    {
        $model = match ($kind) {
            'client' => Client::query()->findOrFail($id),
            'vendor' => Vendor::query()->findOrFail($id),
            'person' => Person::query()->findOrFail($id),
            default => abort(404),
        };
        $model->forceFill(['is_pinned' => ! $model->is_pinned])->save();

        return response()->json(['pinned' => (bool) $model->is_pinned]);
    }
}
