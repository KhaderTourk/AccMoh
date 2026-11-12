<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * نظام التصميم (Living Style Guide، 12/11/2026): صفحة للمدير فيها كل عناصر النظام بكل حالاتها،
 * مبنية بنفس الـCSS والأجزاء المشتركة، وبأرقام وهمية بس. أي عنصر جديد بالنظام بينضاف هون بنفس التعديل.
 * الإرشادات المكتوبة: docs/design-system.md.
 */
class StyleGuideController extends Controller
{
    public function index()
    {
        $pages = new LengthAwarePaginator(range(1, 20), 120, 20, 2, ['path' => route('cp.style')]);

        return view('cp.style', ['pages' => $pages]);
    }
}
