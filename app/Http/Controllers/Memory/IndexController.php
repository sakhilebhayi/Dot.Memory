<?php

namespace App\Http\Controllers\Memory;

use App\Http\Controllers\Controller;

class IndexController extends Controller
{
    public function index()
    {
        return view('indexes.index');
    }
}
