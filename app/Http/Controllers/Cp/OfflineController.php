<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;

class OfflineController extends Controller
{
    public function index()
    {
        return view('cp.offline.index');
    }
}
