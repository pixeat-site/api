<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Log TUDO que chega no Laravel
        Log::info('🔍 [REQUEST DEBUG] ====================================');
        Log::info('Method: ' . $request->method());
        Log::info('URL: ' . $request->fullUrl());
        Log::info('Headers: ' . json_encode($request->headers->all()));
        Log::info('Content-Type: ' . $request->header('Content-Type'));
        Log::info('Raw Body: ' . $request->getContent());
        Log::info('Parsed Input: ' . json_encode($request->all()));
        Log::info('Has JSON: ' . ($request->isJson() ? 'YES' : 'NO'));
        Log::info('====================================================');

        return $next($request);
    }
}

