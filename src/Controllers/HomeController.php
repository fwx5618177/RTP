<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

class HomeController extends BaseController
{
    public function index(Request $request): Response
    {
        return new Response([
            'message' => 'Welcome to RTP Bridge API',
            'version' => '1.0.0',
            'status' => 'running',
        ]);
    }
}
