<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveSubscription
{
    /**
     * Handle an incoming request.
     * التحقق من وجود اشتراك نشط للمستخدم قبل السماح بإضافة مهام أو مشاركين
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // التحقق من تسجيل دخول المستخدم
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب تسجيل الدخول أولاً'
                ], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // التحقق من وجود اشتراك نشط
        if (!$user->hasActiveSubscription()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب أن يكون لديك اشتراك نشط لإجراء هذه العملية'
                ], 403);
            }
            return redirect()->route('packages.index')
                ->with('error', 'يجب أن يكون لديك اشتراك نشط لإجراء هذه العملية');
        }

        return $next($request);
    }
}