<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    public function handle(Request $request, Closure $next, int $minutes = 5): Response
    {
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $key = 'api_cache_' . md5($request->fullUrl() . auth()->id());
        
        return Cache::remember($key, $minutes * 60, function () use ($next, $request) {
            return $next($request);
        });
    }
}