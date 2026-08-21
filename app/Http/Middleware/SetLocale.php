<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $locales = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        // استنتاج اللغة: إن كان أول جزء في المسار "en" فإنجليزي، وإلا عربي (لا 404)
        $path = trim($request->path(), '/');
        $firstSegment = $path !== '' ? explode('/', $path)[0] : '';

        if ($firstSegment === 'en') {
            App::setLocale('en');
            session(['locale' => 'en']);
        } else {
            // أي مسار آخر أو بدون بادئة = العربية
            $sessionLocale = session('locale');
            if ($sessionLocale && in_array($sessionLocale, $this->locales, true)) {
                App::setLocale($sessionLocale);
            } else {
                App::setLocale('ar');
            }
        }

        // عند توليد الروابط: للعربية لا بادئة، للإنجليزية نضيف /en (تمرير مصفوفة دائماً لتجنب خطأ array_merge)
        $locale = App::getLocale();
        URL::defaults([
            'locale' => $locale === 'ar' ? null : $locale,
        ]);

        return $next($request);
    }
}
