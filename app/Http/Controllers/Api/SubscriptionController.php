<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrialStatusResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\MobilePaymentRequest;
use App\Http\Resources\MobilePaymentResource;
use App\Models\Subscription;
use App\Models\Package;
use App\Services\PayTabsService;
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

    public function createPaymentForMobile(MobilePaymentRequest $request): JsonResponse
    {
        $user = auth()->user();
        $packageId = $request->package_id;
        
        // كاش للباقة لمدة 5 دقائق
        $package = Cache::remember("package_{$packageId}", 300, function() use ($packageId) {
            return Package::select('id', 'name', 'price', 'is_active', 'is_trial', 'max_tasks', 'max_milestones_per_task')
                ->where('id', $packageId)
                ->where('is_active', true)
                ->first();
        });

        if (!$package) {
            return response()->json([
                'message' => 'الباقة غير موجودة أو غير نشطة حاليًا.',
                'error' => 'package_not_found_or_inactive'
            ], 404);
        }

        // فحص سريع للاشتراك النشط
        if (Subscription::where('user_id', $user->id)->where('status', 'active')->where('end_date', '>', now())->exists()) {
            return response()->json([
                'message' => 'لديك اشتراك نشط بالفعل.',
                'error' => 'active_subscription_exists'
            ], 422);
        }

        // الباقة التجريبية - اشتراك مباشر
        if ($package->is_trial) {
            // فحص سريع للتجربة السابقة
            if (Subscription::where('user_id', $user->id)->whereHas('package', fn($q) => $q->where('is_trial', true))->exists()) {
                return response()->json(['message' => 'لقد استفدت من المدة التجريبية من قبل.', 'error' => 'trial_period_used'], 422);
            }
            
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'status' => 'active',
                'start_date' => now(),
                'end_date' => now()->addDays(30),
                'price_paid' => 0,
                'tasks_created' => 0,
                'max_tasks' => $package->max_tasks,
                'max_milestones_per_task' => $package->max_milestones_per_task
            ]);
            
            return response()->json(['message' => 'تم تفعيل الباقة بنجاح', 'subscription_id' => $subscription->id, 'status' => 'active'], 201);
        }



        // فحص سعر الباقة
        if ($package->price <= 0) {
            return response()->json(['message' => 'هذه الباقة لا تتطلب دفعًا.', 'error' => 'no_payment_required'], 422);
        }

        // فحص بيانات المستخدم أولاً
        if (empty($user->name) || empty($user->email)) {
            return response()->json(['message' => 'بيانات المستخدم غير مكتملة.', 'error' => 'incomplete_user_data'], 422);
        }

        // إنشاء سجل الدفع
        $payment = \App\Models\Payment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_id' => 'payment_' . uniqid(),
            'amount' => $package->price,
            'currency' => 'SAR',
            'status' => 'pending',
            'gateway' => 'paytabs',
        ]);

        // إنشاء رابط الدفع
        try {
            $paymentResult = (new PayTabsService())->createPaymentPage([
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

            return response()->json([
                'message' => 'تم إنشاء رابط الدفع بنجاح',
                'payment_url' => $paymentResult['payment_url'] ?? null,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ أثناء إنشاء رابط الدفع.', 'error' => 'payment_url_creation_failed'], 500);
        }
    }
}