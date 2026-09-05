<?php

namespace App\Domains\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'engineVersion' => config('matching.engine_version'),
            'tolerance' => config('matching.tolerance'),
        ]);
    }
}
