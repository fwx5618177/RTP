<?php

namespace App\Interfaces;

use App\Http\Request;
use App\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): ?Response;
}
