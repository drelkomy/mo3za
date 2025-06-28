<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ClearAndOptimizeCacheJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    /**
     * تنظيف الكاش يدوياً
     */
    public function clearCache(Request $request): JsonResponse
    {
        
        try {
            ClearAndOptimizeCacheJob::dispatch();
            
            return response()->json([
                'message' => 'تم إرسال مهمة تنظيف الكاش إلى الطابور',
                'status' => 'success'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطأ في تنظيف الكاش',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}