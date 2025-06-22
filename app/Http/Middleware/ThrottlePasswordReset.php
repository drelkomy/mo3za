<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottlePasswordReset
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'password-reset:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "تم تجاوز الحد المسموح. حاول مرة أخرى بعد {$seconds} ثانية"
            ], 429);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        return $next($request);
    }
}