<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * «ضل مكانك»: بعد أي حركة (حفظ/حذف) بترجع لنفس الشاشة، إذا التحويل رايح لنفس الصفحة اللي كنت فيها
 * بدون فلاتر، بنرجّع فلاتر الرابط اللي كانت مفتوحة (?q=...&from=...) بدل ما ترجع على «الكل».
 * ما بنلمس أي تحويل رايح لصفحة ثانية أو معه فلاتر خاصة فيه.
 */
class KeepListQueryOnRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') || ! $response instanceof RedirectResponse || ! $request->hasSession()) {
            return $response;
        }

        $previous = (string) $request->session()->previousUrl();
        $target = $response->getTargetUrl();
        $prev = parse_url($previous);
        $to = parse_url($target);

        if (! empty($prev['query']) && empty($to['query'])
            && ($prev['host'] ?? null) === ($to['host'] ?? null)
            && rtrim($prev['path'] ?? '/', '/') === rtrim($to['path'] ?? '/', '/')) {
            $response->setTargetUrl($previous);
        }

        return $response;
    }
}
