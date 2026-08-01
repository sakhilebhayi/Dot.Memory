<?php

namespace App\Http\Controllers\Memory;

use App\Http\Controllers\Controller;

class DurabilityController extends Controller
{
    public function index()
    {
        return view('durability.index');
    }
}
