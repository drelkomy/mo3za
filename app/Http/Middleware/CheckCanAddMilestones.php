<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCanAddMilestones
{
    /**
     * Handle an incoming request.
     * التحقق من إمكانية إضافة مراحل للمهام بناءً على الاشتراك
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
        
        // التحقق من إمكانية إضافة مراحل للمهام
        if (!$user->canAddMilestones()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكنك إضافة مراحل للمهام في اشتراكك الحالي'
                ], 403);
            }
            return redirect()->route('packages.index')
                ->with('error', 'لا يمكنك إضافة مراحل للمهام في اشتراكك الحالي');
        }

        return $next($request);
    }
}