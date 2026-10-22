<?php

namespace App\Http\Middleware;

use App\Services\Api\ApiJournal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags every add/edit made through the token API so it shows in the CP log and can be undone.
 */
class RecordApiOperation
{
    public function __construct(protected ApiJournal $journal) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $this->journal->start();
        $response = $next($request);
        $this->journal->finish($request, $response);

        return $response;
    }
}
