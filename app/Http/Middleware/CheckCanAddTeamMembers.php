<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCanAddTeamMembers
{
    /**
     * Handle an incoming request.
     * التحقق من إمكانية إضافة أعضاء للفريق بناءً على الاشتراك
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
        
        // التحقق من إمكانية إضافة مشاركين جدد
        if (!$user->canAddParticipants()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لقد وصلت للحد الأقصى من المشاركين المسموح به في اشتراكك الحالي'
                ], 403);
            }
            return redirect()->route('packages.index')
                ->with('error', 'لقد وصلت للحد الأقصى من المشاركين المسموح به في اشتراكك الحالي');
        }

        return $next($request);
    }
}