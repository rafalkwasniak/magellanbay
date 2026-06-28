<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Renderable
    {
        return view('seller.dashboard', [
            'shop' => $request->user()->shop,
        ]);
    }
}
