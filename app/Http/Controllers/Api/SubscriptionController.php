<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrialStatusResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $subscriptions = auth()->user()->subscriptions()
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'تم جلب الاشتراكات بنجاح',
            'data' => $subscriptions
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id'
        ]);

        $subscription = auth()->user()->subscriptions()
            ->where('id', $request->subscription_id)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود'], 404);
        }

        $subscription->update(['status' => 'cancelled']);

        return response()->json(['message' => 'تم إلغاء الاشتراك بنجاح']);
    }

    public function trialStatus(): JsonResponse
    {
        $cacheKey = 'trial_status_' . auth()->id();
        
        $trialData = Cache::remember($cacheKey, 300, function () {
            $user = auth()->user();
            
            // التحقق من وجود اشتراك تجريبي سابق
            $trialSubscription = $user->subscriptions()
                ->whereHas('package', function($q) {
                    $q->where('is_trial', true);
                })
                ->first();
            
            $hasTrial = $trialSubscription !== null;
            $isTrialActive = $trialSubscription && $trialSubscription->status === 'active' && 
                           $trialSubscription->end_date && $trialSubscription->end_date->isFuture();
            
            return (object) [
                'has_trial' => $hasTrial,
                'is_trial_active' => $isTrialActive,
                'trial_subscription' => $trialSubscription,
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب حالة الاشتراك التجريبي بنجاح',
            'data' => new TrialStatusResource($trialData)
        ])->setMaxAge(300)->setPublic();
    }
}