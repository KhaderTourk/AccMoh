<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يستدعي setLocale() من mcamara/laravel-localization لضبط لغة التطبيق من أول جزء في المسار.
 * مطلوب عند استخدام بادئات ثابتة ('', 'en') بدلاً من prefix LaravelLocalization::setLocale().
 */
class SetLocaleFromUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        app('laravellocalization')->setLocale();

        return $next($request);
    }
}
