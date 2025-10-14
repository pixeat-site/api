<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Log TUDO que chega no Laravel (usando error para aparecer em produção)
        Log::error('🔍 [REQUEST DEBUG] ====================================');
        Log::error('Method: ' . $request->method());
        Log::error('URL: ' . $request->fullUrl());
        Log::error('Headers: ' . json_encode($request->headers->all()));
        Log::error('Authorization Header: ' . $request->header('Authorization'));
        Log::error('Content-Type: ' . $request->header('Content-Type'));
        Log::error('Raw Body: ' . $request->getContent());
        Log::error('Parsed Input: ' . json_encode($request->all()));
        Log::error('Has JSON: ' . ($request->isJson() ? 'YES' : 'NO'));
        Log::error('====================================================');

        return $next($request);
    }
}

