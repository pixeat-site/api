<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Se o body tem JSON, força o Laravel a processar
        $content = $request->getContent();
        
        if (!empty($content) && (str_starts_with(trim($content), '{') || str_starts_with(trim($content), '['))) {
            // Parse JSON e injeta no request
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $request->replace($data);
            }
        }

        return $next($request);
    }
}

