<?php

namespace App\Http\Middleware;

use App\Support\DateRange;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeDisplayDates
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->request->count() || $request->query->count()) {
            $request->merge($this->normalize($request->all()));
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalize($value);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $stored = DateRange::toStorageDate($value);
            if ($stored !== null) {
                $data[$key] = $stored;
            }
        }

        return $data;
    }
}
