<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrialStatusResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Subscription;
use App\Models\Package;
use App\Services\PayTabsService;

class SubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = auth()->id();
        $cacheKey = "user_subscriptions_" . $userId;
        
        $subscriptions = Cache::remember($cacheKey, 300, function () use ($userId) {
            return auth()->user()->subscriptions()
                ->with('package')
                ->orderBy('created_at', 'desc')
                ->get();
        });

        return response()->json([
            'message' => 'تم جلب الاشتراكات بنجاح',
            'data' => \App\Http\Resources\SubscriptionResource::collection($subscriptions)
        ])->setMaxAge(300)->setPublic();
    }

    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id'
        ]);

        $userId = auth()->id();
        $subscription = auth()->user()->subscriptions()
            ->where('id', $request->subscription_id)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود'], 404);
        }

        $subscription->update(['status' => 'cancelled']);
        
        // مسح كاش المستخدم
        Cache::forget("user_subscriptions_" . $userId);
        Cache::forget('user_subscription_' . $userId);

        return response()->json(['message' => 'تم إلغاء الاشتراك بنجاح']);
    }

    public function trialStatus(): JsonResponse
    {
        $userId = auth()->id();
        $cacheKey = "trial_status_" . $userId;
        
        $trialData = Cache::remember($cacheKey, 300, function () use ($userId) {
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

    public function currentSubscription(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $cacheKey = "current_subscription_" . $userId;
        
        $subscription = Cache::remember($cacheKey, 300, function () {
            return auth()->user()->subscriptions()
                ->with('package')
                ->orderBy('created_at', 'desc')
                ->first();
        });
        
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
        ])->setMaxAge(300)->setPublic();
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

    public function createPaymentForMobile(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id'
        ]);

        $user = auth()->user();
        $package = Package::where('id', $request->package_id)
            ->where('is_active', true)
            ->first();

        if (!$package) {
            return response()->json([
                'message' => 'الباقة غير موجودة أو غير نشطة حاليًا.',
                'error' => 'package_not_found_or_inactive'
            ], 404);
        }

        // التحقق من وجود اشتراك نشط
        $activeSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->first();

        if ($activeSubscription) {
            return response()->json([
                'message' => 'لديك اشتراك نشط بالفعل. لا يمكنك الاشتراك في باقة جديدة حتى انتهاء الاشتراك الحالي.',
                'error' => 'active_subscription_exists'
            ], 422);
        }

        // التحقق من استخدام المدة التجريبية إذا كانت الباقة تجريبية
        if ($package->is_trial) {
            $trialSubscription = $user->subscriptions()
                ->whereHas('package', function($q) {
                    $q->where('is_trial', true);
                })
                ->first();

            if ($trialSubscription) {
                return response()->json([
                    'message' => 'لقد استفدت من المدة التجريبية من قبل. لا يمكنك الاشتراك في باقة تجريبية مرة أخرى.',
                    'error' => 'trial_period_used'
                ], 422);
            }
            // إذا كانت الباقة تجريبية، لا حاجة للدفع، يتم التفعيل مباشرة
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'status' => 'active',
                'start_date' => now(),
                'end_date' => now()->addDays($package->duration_days)
            ]);
            
            // مسح كاش المستخدم
            Cache::forget('user_subscription_' . $user->id);
            Cache::forget('user_subscriptions_' . $user->id);
            Cache::forget('current_subscription_' . $user->id);
            Cache::forget('trial_status_' . $user->id);
            
            return response()->json([
                'message' => 'تم تفعيل الباقة التجريبية بنجاح',
                'subscription_id' => $subscription->id,
            ]);
        }

        // التحقق من أن الباقة تتطلب دفعًا (ليست مجانية أو تجريبية)
        if ($package->price <= 0) {
            return response()->json([
                'message' => 'هذه الباقة لا تتطلب دفعًا. يمكن تفعيلها مباشرة.',
                'error' => 'no_payment_required'
            ], 422);
        }

        // إنشاء سجل دفع في جدول المدفوعات بحالة pending
        $payment = \App\Models\Payment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_id' => 'payment_' . uniqid(),
            'amount' => $package->price,
            'currency' => 'SAR',
            'status' => 'pending',
            'gateway' => 'paytabs',
        ]);

        // التحقق من اكتمال بيانات المستخدم
        if (empty($user->name) || empty($user->email)) {
            return response()->json([
                'message' => 'بيانات المستخدم غير مكتملة. يرجى تحديث ملفك الشخصي باسمك وبريدك الإلكتروني قبل المتابعة.',
                'error' => 'incomplete_user_data'
            ], 422);
        }

        // إنشاء رابط الدفع باستخدام PayTabsService
        try {
            $payTabsService = new PayTabsService();
            $paymentResult = $payTabsService->createPaymentPage([
                'order_id' => $payment->order_id,
                'description' => 'اشتراك باقة ' . $package->name,
                'currency' => 'SAR',
                'amount' => floatval($package->price),
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone ?? '0000000000',
                'callback_url' => url('/api/payment/callback'),
                'return_url_success' => url('/api/payment/success/' . $payment->id),
                'return_url_cancel' => url('/api/payment/cancel/' . $payment->id),
            ]);

            if (!isset($paymentResult['payment_url'])) {
                throw new \Exception('فشل في إنشاء رابط الدفع: الاستجابة غير مكتملة');
            }

            // إعادة الرابط إلى التطبيق
            return response()->json([
                'message' => 'تم إنشاء رابط الدفع بنجاح',
                'payment_url' => $paymentResult['payment_url'],
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            // تسجيل الخطأ للتصحيح
            \Illuminate\Support\Facades\Log::error('Payment URL creation failed', [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // إرجاع رسالة خطأ للمستخدم
            return response()->json([
                'message' => 'حدث خطأ أثناء إنشاء رابط الدفع. يرجى المحاولة لاحقًا.',
                'error' => 'payment_url_creation_failed'
            ], 500);
        }
    }
}
