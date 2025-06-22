<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        
        $cacheKey = 'user_subscriptions_' . auth()->id() . '_page_' . $page . '_' . $perPage;
        
        $subscriptionsData = Cache::remember($cacheKey, 300, function () use ($page, $perPage) {
            $query = auth()->user()->subscriptions()
                ->with('package:id,name,price,max_tasks,is_trial')
                ->select(['id', 'package_id', 'status', 'start_date', 'created_at'])
                ->orderBy('created_at', 'desc');
                
            $total = $query->count();
            $subscriptions = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
                
            return [
                'data' => $subscriptions,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });

        return response()->json([
            'message' => 'تم جلب الاشتراكات بنجاح',
            'data' => SubscriptionResource::collection($subscriptionsData['data']),
            'meta' => [
                'total' => $subscriptionsData['total'],
                'current_page' => $subscriptionsData['current_page'],
                'per_page' => $subscriptionsData['per_page'],
                'last_page' => $subscriptionsData['last_page']
            ]
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'id' => 'required|exists:subscriptions,id'
        ], [
            'id.required' => 'معرف الاشتراك مطلوب',
            'id.exists' => 'الاشتراك غير موجود'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }
        
        $key = 'cancel_subscription:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 2)) {
            return response()->json(['message' => 'عدد كبير من محاولات الإلغاء. يرجى المحاولة لاحقاً.'], 429);
        }
        
        $subscriptionId = $request->input('id');
        $subscription = Subscription::findOrFail($subscriptionId);
        
        if ($subscription->user_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بإلغاء هذا الاشتراك'], 403);
        }

        if ($subscription->status !== 'active') {
            return response()->json(['message' => 'لا يمكن إلغاء اشتراك غير نشط'], 422);
        }

        $subscription->update(['status' => 'cancelled']);

        Cache::forget('user_subscriptions_' . auth()->id());
        Cache::forget('user_subscription_' . auth()->id());

        RateLimiter::hit($key, 300);

        return response()->json([
            'message' => 'تم إلغاء الاشتراك بنجاح',
            'subscription_id' => $subscription->id
        ]);
    }
}