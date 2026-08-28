<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gzip compressible HTML/JSON/CSS/JS responses when the client accepts it.
 * Helps a lot on slow mobile networks for Laravel pages and API payloads.
 */
class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($response->headers->has('Content-Encoding')) {
            return $response;
        }

        $accept = (string) $request->header('Accept-Encoding', '');
        if (! str_contains($accept, 'gzip') || ! function_exists('gzencode')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        $compressible = str_contains($contentType, 'text/')
            || str_contains($contentType, 'json')
            || str_contains($contentType, 'javascript')
            || str_contains($contentType, 'xml');

        if (! $compressible) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '' || strlen($content) < 1024) {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding', false);
        $response->headers->remove('Content-Length');

        return $response;
    }
}
