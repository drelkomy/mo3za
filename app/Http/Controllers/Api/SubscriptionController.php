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
            'data' => \App\Http\Resources\SubscriptionResource::collection($subscriptions)
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
        
        $trialData = (object) [
            'has_trial' => $hasTrial,
            'is_trial_active' => $isTrialActive,
            'trial_subscription' => $trialSubscription,
        ];
        
        return response()->json([
            'message' => 'تم جلب حالة الاشتراك التجريبي بنجاح',
            'data' => new TrialStatusResource($trialData)
        ]);
    }

    public function currentSubscription(Request $request): JsonResponse
    {
        $subscription = auth()->user()->subscriptions()
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$subscription) {
            return response()->json([
                'message' => 'لا يوجد اشتراك حالياً',
                'data' => null,
                'has_subscription' => false
            ]);
        }
        
        return response()->json([
            'message' => 'تم جلب اشتراكك الحالي بنجاح',
            'data' => new \App\Http\Resources\CurrentSubscriptionResource($subscription),
            'has_subscription' => true
        ]);
    }

    public function subscriptionHistory(): JsonResponse
    {
        $subscriptions = auth()->user()->subscriptions()
            ->with(['package', 'tasks'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'message' => 'تم جلب تاريخ الاشتراكات والمهام بنجاح',
            'data' => \App\Http\Resources\SubscriptionWithTasksResource::collection($subscriptions)
        ]);
    }
}
