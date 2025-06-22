<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        Log::info('Payment callback received', $request->all());
        
        $subscriptionId = $request->input('subscription_id');
        $status = $request->input('status'); // success, failed, pending
        $transactionId = $request->input('transaction_id');
        
        $subscription = Subscription::find($subscriptionId);
        
        if (!$subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود'], 404);
        }
        
        if ($status === 'success') {
            $subscription->update([
                'status' => 'active',
                'transaction_id' => $transactionId,
                'activated_at' => now()
            ]);
            
            Cache::forget('user_subscription_' . $subscription->user_id);
            
            return response()->json(['message' => 'تم تفعيل الاشتراك بنجاح']);
        }
        
        return response()->json(['message' => 'فشل في الدفع'], 400);
    }
    
    public function success(Subscription $subscription): JsonResponse
    {
        return response()->json([
            'message' => 'تم الدفع بنجاح',
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'package_name' => $subscription->package->name
            ]
        ]);
    }
    
    public function cancel(Subscription $subscription): JsonResponse
    {
        return response()->json([
            'message' => 'تم إلغاء عملية الدفع',
            'subscription_id' => $subscription->id
        ]);
    }
}